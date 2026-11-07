<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\Message;

/**
 * Mensagem assíncrona para enviar a redefinição de senha.
 *
 * O token bruto vai cifrado; o hash fica em claro para permitir o consumo do
 * token quando a entrega falha definitivamente.
 */
final readonly class SendPasswordResetMessage
{
    public function __construct(
        public string $email,
        public string $encryptedToken,
        public int $expiresAtTimestamp,
        public string $tokenHash,
    ) {
    }
}
