<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\MessageHandler;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Conteúdo já decifrado e validado de uma mensagem de e-mail.
 */
final readonly class DecodedIdentityEmail
{
    public function __construct(
        public Email $to,
        public string $rawToken,
        public Instant $expiresAt,
    ) {
    }
}
