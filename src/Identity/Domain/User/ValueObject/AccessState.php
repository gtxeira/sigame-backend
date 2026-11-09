<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use Doctrine\ORM\Mapping as ORM;

/**
 * Estado de acesso do usuário (autenticação e autorização).
 *
 * Concentra papel e situação da conta, isolando as regras que o fluxo de
 * autenticação consulta (RN18: conta suspensa não autentica).
 */
#[ORM\Embeddable]
final readonly class AccessState
{
    private function __construct(
        #[ORM\Column(name: 'role', type: 'string', length: 10, enumType: UserRole::class)]
        private UserRole $role,

        #[ORM\Column(name: 'status', type: 'string', length: 20, enumType: UserStatus::class)]
        private UserStatus $status,
    ) {
    }

    public static function initial(): self
    {
        return new self(UserRole::USER, UserStatus::ACTIVE);
    }

    /**
     * Suspende a conta.
     *
     * RN18.3: Conta suspensa não pode autenticar.
     */
    public function suspend(): self
    {
        if (UserStatus::SUSPENDED === $this->status) {
            return $this;
        }

        return new self($this->role, UserStatus::SUSPENDED);
    }

    public function reactivate(): self
    {
        if (UserStatus::ACTIVE === $this->status) {
            return $this;
        }

        return new self($this->role, UserStatus::ACTIVE);
    }

    /**
     * Indica se a conta pode autenticar.
     *
     * RN18.3: Conta suspensa não pode autenticar.
     */
    public function canAuthenticate(): bool
    {
        return UserStatus::ACTIVE === $this->status;
    }

    public function isActive(): bool
    {
        return UserStatus::ACTIVE === $this->status;
    }

    public function isSuspended(): bool
    {
        return UserStatus::SUSPENDED === $this->status;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }
}
