<?php

declare(strict_types=1);

use App\Identity\Infrastructure\Mailer\MessageHandler\IdentityEmailMessageDecoder;
use App\Shared\Infrastructure\Crypto\EncryptedPayload;
use App\Shared\Infrastructure\Crypto\PayloadEncryptor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

function identityDecoderTestEncryptor(): PayloadEncryptor
{
    return new PayloadEncryptor(base64_encode(str_repeat('d', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
}

function identityDecoderTestPayload(string $code): string
{
    return identityDecoderTestEncryptor()->encrypt($code)->toBase64();
}

it('decrypts and validates a message payload', function () {
    $code = '123456';
    $decoder = new IdentityEmailMessageDecoder(
        encryptor: identityDecoderTestEncryptor(),
        logger: Mockery::mock(LoggerInterface::class),
    );

    $decoded = $decoder->decode(
        encryptedCode: identityDecoderTestPayload($code),
        email: 'user@example.com',
        expiresAtTimestamp: 1767225600,
        challengeId: '019c0000-0000-7000-8000-000000000000',
    );

    expect($decoded->to->value())->toBe('user@example.com')
        ->and($decoded->code)->toBe($code)
        ->and($decoded->expiresAt->timestamp())->toBe(1767225600);
});

it('throws an unrecoverable exception when the payload cannot be decrypted', function () {
    $encryptor = identityDecoderTestEncryptor();
    $payload = $encryptor->encrypt('123456');

    $ciphertext = $payload->ciphertext();
    $ciphertext[0] = chr(ord($ciphertext[0]) ^ 0xFF);

    $tampered = EncryptedPayload::fromParts($payload->nonce(), $ciphertext)->toBase64();

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once();

    $decoder = new IdentityEmailMessageDecoder($encryptor, $logger);

    expect(static fn () => $decoder->decode($tampered, 'user@example.com', 1767225600, 'challenge'))
        ->toThrow(UnrecoverableMessageHandlingException::class);
});

it('throws an unrecoverable exception when the email is invalid', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once();

    $decoder = new IdentityEmailMessageDecoder(identityDecoderTestEncryptor(), $logger);

    expect(static fn () => $decoder->decode(
        identityDecoderTestPayload('123456'),
        'not-an-email',
        1767225600,
        'challenge',
    ))->toThrow(UnrecoverableMessageHandlingException::class);
});
