<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\User;

use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Application\User\UserActiveReaderInterface;
use App\Shared\Application\User\UserContact;
use App\Shared\Application\User\UserContactReaderInterface;
use App\Shared\Domain\Exception\InvalidUuidException;

/**
 * Adapter Doctrine das portas de leitura de usuário usadas por outros módulos.
 */
final readonly class DoctrineUserContactReader implements UserActiveReaderInterface, UserContactReaderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function isActive(string $userId): bool
    {
        $contact = $this->contact($userId);

        return null !== $contact && $contact->active;
    }

    public function contact(string $userId): ?UserContact
    {
        try {
            $id = UserId::fromString($userId);
        } catch (InvalidUuidException) {
            return null;
        }

        $user = $this->userRepository->findById($id);

        if (null === $user) {
            return null;
        }

        return new UserContact(
            id: $user->id()->value(),
            name: $user->name(),
            email: $user->email()->value(),
            active: $user->isActive(),
        );
    }
}
