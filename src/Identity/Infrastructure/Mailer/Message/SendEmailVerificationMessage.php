<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\Message;

/**
 * Mensagem assíncrona para enviar a verificação de e-mail.
 *
 * O código bruto vai cifrado; o identificador do desafio fica em claro para
 * permitir a compensação do código em caso de falha definitiva.
 */
final readonly class SendEmailVerificationMessage
{
    public function __construct(
        public string $email,
        public string $encryptedCode,
        public int $expiresAtTimestamp,
        public string $challengeId,
    ) {
    }
}
