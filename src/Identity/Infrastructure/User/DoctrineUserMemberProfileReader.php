<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\User;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Application\User\UserMemberProfile;
use App\Shared\Application\User\UserMemberProfileReaderInterface;
use App\Shared\Domain\Exception\InvalidUuidException;

/**
 * Adapter Doctrine dos dados públicos de participantes.
 *
 * Resolve nome, nível e URL da foto. Identificadores inválidos ou contas
 * inexistentes são simplesmente omitidos do mapa.
 */
final readonly class DoctrineUserMemberProfileReader implements UserMemberProfileReaderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ProfilePhotoStorageInterface $photoStorage,
    ) {
    }

    public function profiles(array $userIds): array
    {
        $profiles = [];

        foreach (array_unique($userIds) as $userId) {
            try {
                $id = UserId::fromString($userId);
            } catch (InvalidUuidException) {
                continue;
            }

            $user = $this->userRepository->findById($id);

            if (null === $user) {
                continue;
            }

            $profiles[$userId] = new UserMemberProfile(
                id: $user->id()->value(),
                name: $user->name(),
                level: $user->level()->value(),
                photoUrl: $this->photoStorage->url($user->photoKey()),
            );
        }

        return $profiles;
    }
}
