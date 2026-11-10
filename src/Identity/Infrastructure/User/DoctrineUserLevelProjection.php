<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\User;

use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Domain\User\ValueObject\UserLevel;
use App\Shared\Application\User\UserLevelProjectionInterface;
use App\Shared\Domain\Exception\InvalidUuidException;

/**
 * Adapter Doctrine da porta de projeção de nível.
 *
 * Usado pelo reparo administrativo da gamificação quando `users.level` diverge
 * de `user_progress.level`.
 */
final readonly class DoctrineUserLevelProjection implements UserLevelProjectionInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function project(string $userId, int $level): bool
    {
        try {
            $id = UserId::fromString($userId);
        } catch (InvalidUuidException) {
            return false;
        }

        $levelResult = UserLevel::fromInt($level);

        if ($levelResult->isInvalid()) {
            return false;
        }

        $user = $this->userRepository->findById($id);

        if (null === $user) {
            return false;
        }

        $user->syncLevel($levelResult->value());
        $this->userRepository->save($user);

        return true;
    }
}
