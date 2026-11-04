<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;

/**
 * Identidade devolvida por um provedor social após a troca do authorization code.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public IdentityProvider $provider,
        public ProviderSubject $subject,
        public Email $email,
        public bool $emailVerified,
        public ?string $name = null,
        public ?string $pictureUrl = null,
    ) {
    }
}
