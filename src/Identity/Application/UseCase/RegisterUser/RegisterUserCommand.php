<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\RegisterUser;

/**
 * Command para solicitação de cadastro.
 *
 * O CPF não faz parte do cadastro público para não expor a existência de
 * documentos; ele é definido depois, autenticado, no perfil.
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        private string $email,
        private string $name,
        private string $password,
    ) {
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
