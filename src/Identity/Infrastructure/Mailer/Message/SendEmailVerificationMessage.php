<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\Message;

/**
 * Mensagem assíncrona para enviar a verificação de e-mail.
 *
 * O token bruto vai cifrado; o hash fica em claro para permitir a compensação
 * do token em caso de falha definitiva.
 */
final readonly class SendEmailVerificationMessage
{
    public function __construct(
        public string $email,
        public string $encryptedToken,
        public int $expiresAtTimestamp,
        public string $tokenHash,
    ) {
    }
}
