<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\Exception\InvalidUuidException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Carrega o usuário do domínio a partir do `sub` do access token.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SecurityUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $id = UserId::fromString($identifier);
        } catch (InvalidUuidException) {
            throw new UserNotFoundException('Identificador de usuário inválido.');
        }

        $user = $this->userRepository->findById($id);

        if (null === $user) {
            throw new UserNotFoundException('Usuário não encontrado.');
        }

        return new SecurityUser($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Tipo de usuário não suportado: %s.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }
}
