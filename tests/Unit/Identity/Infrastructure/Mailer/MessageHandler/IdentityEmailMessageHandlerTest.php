<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Identity\Infrastructure\Mailer\Message\SendEmailVerificationMessage;
use App\Identity\Infrastructure\Mailer\Message\SendPasswordResetMessage;
use App\Identity\Infrastructure\Mailer\MessageHandler\IdentityEmailMessageDecoder;
use App\Identity\Infrastructure\Mailer\MessageHandler\SendEmailVerificationHandler;
use App\Identity\Infrastructure\Mailer\MessageHandler\SendPasswordResetHandler;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Crypto\PayloadEncryptor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

function identityHandlerTestEncryptor(): PayloadEncryptor
{
    return new PayloadEncryptor(base64_encode(str_repeat('h', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
}

function identityHandlerTestDecoder(): IdentityEmailMessageDecoder
{
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->zeroOrMoreTimes();

    return new IdentityEmailMessageDecoder(
        encryptor: identityHandlerTestEncryptor(),
        logger: $logger,
    );
}

function identityHandlerTestEncryptedCode(string $code): string
{
    return identityHandlerTestEncryptor()->encrypt($code)->toBase64();
}

it('delivers the email verification through the mailer', function () {
    $code = '123456';

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldReceive('sendEmailVerification')
        ->once()
        ->with(
            Mockery::on(static fn (Email $email): bool => 'verify@example.com' === $email->value()),
            $code,
            Mockery::on(static fn (Instant $expiresAt): bool => 1767225600 === $expiresAt->timestamp()),
        );

    $handler = new SendEmailVerificationHandler(identityHandlerTestDecoder(), $mailer);

    $handler(new SendEmailVerificationMessage(
        email: 'verify@example.com',
        encryptedCode: identityHandlerTestEncryptedCode($code),
        expiresAtTimestamp: 1767225600,
        challengeId: '019c0000-0000-7000-8000-000000000000',
    ));
});

it('delivers the password reset through the mailer', function () {
    $code = '654321';

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldReceive('sendPasswordReset')
        ->once()
        ->with(
            Mockery::on(static fn (Email $email): bool => 'reset@example.com' === $email->value()),
            $code,
            Mockery::on(static fn (Instant $expiresAt): bool => 1767225600 === $expiresAt->timestamp()),
        );

    $handler = new SendPasswordResetHandler(identityHandlerTestDecoder(), $mailer);

    $handler(new SendPasswordResetMessage(
        email: 'reset@example.com',
        encryptedCode: identityHandlerTestEncryptedCode($code),
        expiresAtTimestamp: 1767225600,
        challengeId: '019c0000-0000-7000-8000-000000000000',
    ));
});

it('does not call the mailer when the payload is invalid', function () {
    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldNotReceive('sendPasswordReset');

    $handler = new SendPasswordResetHandler(identityHandlerTestDecoder(), $mailer);

    expect(static fn () => $handler(new SendPasswordResetMessage(
        email: 'reset@example.com',
        encryptedCode: 'invalid-payload',
        expiresAtTimestamp: 1767225600,
        challengeId: '019c0000-0000-7000-8000-000000000000',
    )))->toThrow(UnrecoverableMessageHandlingException::class);
});
