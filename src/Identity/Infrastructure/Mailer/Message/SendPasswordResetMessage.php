<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\Message;

/**
 * Mensagem assíncrona para enviar a redefinição de senha.
 *
 * O código bruto vai cifrado; o identificador do desafio fica em claro para
 * permitir o consumo do código quando a entrega falha definitivamente.
 */
final readonly class SendPasswordResetMessage
{
    public function __construct(
        public string $email,
        public string $encryptedCode,
        public int $expiresAtTimestamp,
        public string $challengeId,
    ) {
    }
}
