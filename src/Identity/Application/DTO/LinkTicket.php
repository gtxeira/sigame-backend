<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Identidade social já verificada, aguardando vínculo com uma conta local.
 *
 * Fica no cache associada ao hash do token entregue no 409; o token bruto
 * nunca é persistido.
 */
final readonly class LinkTicket
{
    public function __construct(
        public IdentityProvider $provider,
        public ProviderSubject $subject,
        public Email $email,
        public Instant $expiresAt,
    ) {
    }

    public function isExpiredAt(Instant $now): bool
    {
        return !$this->expiresAt->isAfter($now);
    }
}
