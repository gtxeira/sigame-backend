<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Social\StartSocialFlow;

use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Inicia um fluxo OAuth. Quando há usuário autenticado, a finalidade é vínculo.
 */
final readonly class StartSocialFlowCommand
{
    public function __construct(
        private string $provider,
        private string $client,
        private ?UserId $userId = null,
    ) {
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function client(): string
    {
        return $this->client;
    }

    public function userId(): ?UserId
    {
        return $this->userId;
    }
}
