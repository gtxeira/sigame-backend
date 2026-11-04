<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\UserRole;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adapta o agregado User ao contrato de usuário do SecurityBundle.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private User $user,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->user->id()->value();
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return match ($this->user->role()) {
            UserRole::ADMIN_SIGAME => ['ROLE_ADMIN_SIGAME', 'ROLE_USER'],
            UserRole::USER => ['ROLE_USER'],
        };
    }

    public function getPassword(): ?string
    {
        return $this->user->password()?->value();
    }

    public function user(): User
    {
        return $this->user;
    }
}
