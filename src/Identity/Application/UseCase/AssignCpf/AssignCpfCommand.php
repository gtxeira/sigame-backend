<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\AssignCpf;

use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Command para definir o CPF do usuário autenticado.
 */
final readonly class AssignCpfCommand
{
    public function __construct(
        private UserId $userId,
        private string $cpf,
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function cpf(): string
    {
        return $this->cpf;
    }
}
