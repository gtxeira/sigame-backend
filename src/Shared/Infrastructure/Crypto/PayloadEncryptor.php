<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Crypto;

use App\Shared\Infrastructure\Crypto\Exception\PayloadDecryptionFailed;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use const SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

/**
 * Cifra e decifra payloads com XSalsa20-Poly1305 (libsodium secretbox).
 *
 * A chave deve ser um base64 de exatamente 32 bytes e é validada no boot.
 */
final readonly class PayloadEncryptor
{
    private string $key;

    public function __construct(
        #[Autowire(env: 'MESSENGER_MAIL_ENCRYPTION_KEY')]
        string $base64Key,
    ) {
        $key = base64_decode(trim($base64Key), true);

        if (false === $key || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($key)) {
            throw new InvalidArgumentException(
                'MESSENGER_MAIL_ENCRYPTION_KEY deve ser um base64 de exatamente 32 bytes.',
            );
        }

        $this->key = $key;
    }

    public function encrypt(string $plaintext): EncryptedPayload
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return EncryptedPayload::fromParts(
            $nonce,
            sodium_crypto_secretbox($plaintext, $nonce, $this->key),
        );
    }

    public function decrypt(EncryptedPayload $payload): string
    {
        $plaintext = sodium_crypto_secretbox_open(
            $payload->ciphertext(),
            $payload->nonce(),
            $this->key,
        );

        if (false === $plaintext) {
            throw new PayloadDecryptionFailed('Não foi possível decifrar o payload.');
        }

        return $plaintext;
    }
}
