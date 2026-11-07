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

function identityDecoderTestPayload(string $rawToken): string
{
    return identityDecoderTestEncryptor()->encrypt($rawToken)->toBase64();
}

it('decrypts and validates a message payload', function () {
    $rawToken = 'raw-token';
    $decoder = new IdentityEmailMessageDecoder(
        encryptor: identityDecoderTestEncryptor(),
        logger: Mockery::mock(LoggerInterface::class),
    );

    $decoded = $decoder->decode(
        encryptedToken: identityDecoderTestPayload($rawToken),
        email: 'user@example.com',
        expiresAtTimestamp: 1767225600,
        tokenHash: hash('sha256', $rawToken),
    );

    expect($decoded->to->value())->toBe('user@example.com')
        ->and($decoded->rawToken)->toBe($rawToken)
        ->and($decoded->expiresAt->timestamp())->toBe(1767225600);
});

it('throws an unrecoverable exception when the payload cannot be decrypted', function () {
    $encryptor = identityDecoderTestEncryptor();
    $payload = $encryptor->encrypt('raw-token');

    $ciphertext = $payload->ciphertext();
    $ciphertext[0] = chr(ord($ciphertext[0]) ^ 0xFF);

    $tampered = EncryptedPayload::fromParts($payload->nonce(), $ciphertext)->toBase64();

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once();

    $decoder = new IdentityEmailMessageDecoder($encryptor, $logger);

    expect(static fn () => $decoder->decode($tampered, 'user@example.com', 1767225600, 'hash'))
        ->toThrow(UnrecoverableMessageHandlingException::class);
});

it('throws an unrecoverable exception when the email is invalid', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once();

    $decoder = new IdentityEmailMessageDecoder(identityDecoderTestEncryptor(), $logger);

    expect(static fn () => $decoder->decode(
        identityDecoderTestPayload('raw-token'),
        'not-an-email',
        1767225600,
        'hash',
    ))->toThrow(UnrecoverableMessageHandlingException::class);
});
