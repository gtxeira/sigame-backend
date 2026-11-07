<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Infrastructure\Mailer\Message\SendEmailVerificationMessage;
use App\Identity\Infrastructure\Mailer\Message\SendPasswordResetMessage;
use App\Identity\Infrastructure\Mailer\MessengerIdentityMailDispatcher;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Crypto\EncryptedPayload;
use App\Shared\Infrastructure\Crypto\PayloadEncryptor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

function mailDispatcherTestEncryptor(): PayloadEncryptor
{
    return new PayloadEncryptor(base64_encode(str_repeat('m', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
}

/**
 * @return array{dispatcher: IdentityMailDispatcherInterface, messages: list<object>}
 */
function mailDispatcherTestSubject(): array
{
    $messages = [];

    $bus = Mockery::mock(MessageBusInterface::class);
    $bus->shouldReceive('dispatch')
        ->once()
        ->andReturnUsing(static function (object $message) use (&$messages): Envelope {
            $messages[] = $message;

            return new Envelope($message);
        });

    return [
        'dispatcher' => new MessengerIdentityMailDispatcher(
            bus: $bus,
            encryptor: mailDispatcherTestEncryptor(),
        ),
        'messages' => &$messages,
    ];
}

it('dispatches an encrypted password reset message', function () {
    $subject = mailDispatcherTestSubject();
    $rawToken = 'raw-reset-token';
    $expiresAt = Instant::fromDateTime(new DateTimeImmutable('2026-01-01T00:30:00+00:00'));

    $subject['dispatcher']->dispatchPasswordReset(
        to: Email::fromString('user@example.com')->value(),
        rawToken: $rawToken,
        expiresAt: $expiresAt,
        tokenHash: hash('sha256', $rawToken),
    );

    $message = $subject['messages'][0];
    expect($message)->toBeInstanceOf(SendPasswordResetMessage::class);

    assert($message instanceof SendPasswordResetMessage);

    $decrypted = mailDispatcherTestEncryptor()->decrypt(
        EncryptedPayload::fromBase64($message->encryptedToken),
    );

    expect($message->email)->toBe('user@example.com')
        ->and($message->tokenHash)->toBe(hash('sha256', $rawToken))
        ->and($message->expiresAtTimestamp)->toBe($expiresAt->timestamp())
        ->and($decrypted)->toBe($rawToken)
        ->and(serialize($message))->not->toContain($rawToken);
});

it('dispatches an encrypted email verification message', function () {
    $subject = mailDispatcherTestSubject();
    $rawToken = 'raw-verification-token';

    $subject['dispatcher']->dispatchEmailVerification(
        to: Email::fromString('verify@example.com')->value(),
        rawToken: $rawToken,
        expiresAt: Instant::now(),
        tokenHash: hash('sha256', $rawToken),
    );

    $message = $subject['messages'][0];
    expect($message)->toBeInstanceOf(SendEmailVerificationMessage::class);

    assert($message instanceof SendEmailVerificationMessage);

    $decrypted = mailDispatcherTestEncryptor()->decrypt(
        EncryptedPayload::fromBase64($message->encryptedToken),
    );

    expect($message->email)->toBe('verify@example.com')
        ->and($message->tokenHash)->toBe(hash('sha256', $rawToken))
        ->and($decrypted)->toBe($rawToken)
        ->and(serialize($message))->not->toContain($rawToken);
});
