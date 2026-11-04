<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\AuthenticateUser;

/**
 * Command de login com e-mail e senha.
 */
final readonly class AuthenticateUserCommand
{
    public function __construct(
        private string $email,
        private string $password,
    ) {
    }

    public function email(): string
    {
        return $this->email;
    }

    public function password(): string
    {
        return $this->password;
    }
}
