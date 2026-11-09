<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\User;

use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Application\User\UserLevelReaderInterface;
use App\Shared\Domain\Exception\InvalidUuidException;

/**
 * Adapter Doctrine da porta de leitura de nível.
 *
 * O módulo Groups importa apenas a interface em Shared; esta implementação
 * permanece dentro de Identity, que é dono do dado.
 */
final readonly class DoctrineUserLevelReader implements UserLevelReaderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function levelOf(string $userId): ?int
    {
        try {
            $id = UserId::fromString($userId);
        } catch (InvalidUuidException) {
            return null;
        }

        return $this->userRepository->findById($id)?->level()->value();
    }
}
