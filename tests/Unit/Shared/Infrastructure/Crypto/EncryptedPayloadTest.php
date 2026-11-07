<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Crypto\EncryptedPayload;

it('round-trips a payload through base64', function () {
    $nonce = str_repeat('n', \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = str_repeat('c', \SODIUM_CRYPTO_SECRETBOX_MACBYTES + 4);

    $payload = EncryptedPayload::fromParts($nonce, $ciphertext);
    $decoded = EncryptedPayload::fromBase64($payload->toBase64());

    expect($decoded->nonce())->toBe($nonce)
        ->and($decoded->ciphertext())->toBe($ciphertext);
});

it('rejects a nonce with the wrong length', function () {
    expect(static fn () => EncryptedPayload::fromParts(
        'short',
        str_repeat('c', \SODIUM_CRYPTO_SECRETBOX_MACBYTES),
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects ciphertext shorter than the authentication tag', function () {
    expect(static fn () => EncryptedPayload::fromParts(
        str_repeat('n', \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        'short',
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid base64', function () {
    expect(static fn () => EncryptedPayload::fromBase64('not base64 !!!'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a base64 payload shorter than nonce plus tag', function () {
    expect(static fn () => EncryptedPayload::fromBase64(base64_encode('too-short')))
        ->toThrow(InvalidArgumentException::class);
});
