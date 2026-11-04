<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Logout;

use App\Identity\Application\Service\TokenPairIssuerInterface;

/**
 * Revoga a sessão atual e/ou todas as sessões do usuário.
 */
final readonly class LogoutHandler
{
    public function __construct(
        private TokenPairIssuerInterface $tokenPairIssuer,
    ) {
    }

    public function __invoke(LogoutCommand $command): void
    {
        if ($command->allDevices() && null !== $command->userId()) {
            $this->tokenPairIssuer->revokeAllSessions($command->userId());

            return;
        }

        if (null !== $command->refreshToken()) {
            $this->tokenPairIssuer->revokeCurrentSession($command->refreshToken());
        }
    }
}
