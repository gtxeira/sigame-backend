<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

use App\Shared\Domain\ValueObject\Instant;

/**
 * Ticket de vínculo entregue uma única vez ao cliente.
 */
final readonly class IssuedLinkTicket
{
    public function __construct(
        public string $token,
        public Instant $expiresAt,
    ) {
    }
}
