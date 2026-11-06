<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Social\LinkSocialIdentity;

use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Vínculo iniciado pela tela de configurações, com um novo consentimento.
 */
final readonly class LinkWithFlowCommand
{
    public function __construct(
        private string $provider,
        private string $flowId,
        private string $state,
        private string $authorizationCode,
        private UserId $userId,
    ) {
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function flowId(): string
    {
        return $this->flowId;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function authorizationCode(): string
    {
        return $this->authorizationCode;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }
}
