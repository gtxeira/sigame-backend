<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\RegisterUser;

/**
 * Command para registro de novo usuário.
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        private string $cpf,
        private string $email,
        private string $name,
        private string $password,
    ) {
    }

    public function cpf(): string
    {
        return $this->cpf;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function password(): string
    {
        return $this->password;
    }
}
