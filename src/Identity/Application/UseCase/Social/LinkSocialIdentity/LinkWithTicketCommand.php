<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Social\LinkSocialIdentity;

use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Vínculo a partir do ticket devolvido no 409, após o login local.
 */
final readonly class LinkWithTicketCommand
{
    public function __construct(
        private string $provider,
        private string $linkToken,
        private UserId $userId,
    ) {
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function linkToken(): string
    {
        return $this->linkToken;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }
}
