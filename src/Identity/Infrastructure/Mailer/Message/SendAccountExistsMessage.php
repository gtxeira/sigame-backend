<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\Message;

/**
 * Mensagem assíncrona do aviso de que o e-mail já possui conta.
 *
 * Não carrega código nem link: apenas o endereço que receberá as instruções.
 */
final readonly class SendAccountExistsMessage
{
    public function __construct(
        public string $email,
    ) {
    }
}
