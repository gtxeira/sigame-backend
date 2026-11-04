<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Logout;

use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Command de logout.
 *
 * Com `allDevices`, revoga todas as famílias do usuário autenticado. Sem ele,
 * revoga apenas a família do refresh token apresentado.
 */
final readonly class LogoutCommand
{
    public function __construct(
        private ?string $refreshToken = null,
        private bool $allDevices = false,
        private ?UserId $userId = null,
    ) {
    }

    public function refreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function allDevices(): bool
    {
        return $this->allDevices;
    }

    public function userId(): ?UserId
    {
        return $this->userId;
    }
}
