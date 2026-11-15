<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

/**
 * Ticket bruto devolvido após a validação do código de reset.
 */
final readonly class IssuedPasswordResetTicket
{
    public function __construct(
        public string $rawToken,
        public int $expiresIn,
    ) {
    }
}
