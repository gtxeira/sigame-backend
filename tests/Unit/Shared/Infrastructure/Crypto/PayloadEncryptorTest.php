<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Crypto\EncryptedPayload;
use App\Shared\Infrastructure\Crypto\Exception\PayloadDecryptionFailed;
use App\Shared\Infrastructure\Crypto\PayloadEncryptor;

function cryptoTestKey(string $seed = 'k'): string
{
    return base64_encode(str_repeat($seed, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
}

function cryptoTestEncryptor(string $seed = 'k'): PayloadEncryptor
{
    return new PayloadEncryptor(cryptoTestKey($seed));
}

it('encrypts and decrypts a payload', function () {
    $encryptor = cryptoTestEncryptor();

    $payload = $encryptor->encrypt('token-bruto');

    expect($encryptor->decrypt($payload))->toBe('token-bruto');
});

it('uses a different nonce for every encryption', function () {
    $encryptor = cryptoTestEncryptor();

    $first = $encryptor->encrypt('same-token');
    $second = $encryptor->encrypt('same-token');

    expect($first->toBase64())->not->toBe($second->toBase64())
        ->and($encryptor->decrypt($first))->toBe('same-token')
        ->and($encryptor->decrypt($second))->toBe('same-token');
});

it('rejects a tampered ciphertext', function () {
    $encryptor = cryptoTestEncryptor();
    $payload = $encryptor->encrypt('token-bruto');

    $ciphertext = $payload->ciphertext();
    $ciphertext[0] = chr(ord($ciphertext[0]) ^ 0xFF);

    $tampered = EncryptedPayload::fromParts($payload->nonce(), $ciphertext);

    expect(static fn () => $encryptor->decrypt($tampered))
        ->toThrow(PayloadDecryptionFailed::class);
});

it('fails to decrypt with a different key', function () {
    $payload = cryptoTestEncryptor('a')->encrypt('token-bruto');

    expect(static fn () => cryptoTestEncryptor('b')->decrypt($payload))
        ->toThrow(PayloadDecryptionFailed::class);
});

it('rejects an invalid encryption key', function (string $key) {
    expect(static fn () => new PayloadEncryptor($key))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => '',
    'not base64' => '!!!',
    'wrong length' => base64_encode('too-short'),
]);
