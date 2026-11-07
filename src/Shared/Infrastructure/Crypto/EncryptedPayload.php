<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Crypto;

use InvalidArgumentException;

use const SODIUM_CRYPTO_SECRETBOX_MACBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

/**
 * Payload cifrado com nonce e ciphertext autenticado.
 *
 * O formato de transporte é base64(nonce . ciphertext), usado tanto pelo
 * publicador quanto pelo consumidor da mensagem.
 */
final readonly class EncryptedPayload
{
    private function __construct(
        private string $nonce,
        private string $ciphertext,
    ) {
    }

    public static function fromParts(string $nonce, string $ciphertext): self
    {
        if (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES !== strlen($nonce)) {
            throw new InvalidArgumentException('O nonce do payload cifrado é inválido.');
        }

        if (strlen($ciphertext) < SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new InvalidArgumentException('O ciphertext do payload cifrado é inválido.');
        }

        return new self($nonce, $ciphertext);
    }

    public static function fromBase64(string $encoded): self
    {
        $decoded = base64_decode($encoded, true);

        if (false === $decoded
            || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new InvalidArgumentException('O payload cifrado não está em base64 válido.');
        }

        return self::fromParts(
            substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        );
    }

    public function toBase64(): string
    {
        return base64_encode($this->nonce.$this->ciphertext);
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function ciphertext(): string
    {
        return $this->ciphertext;
    }
}
