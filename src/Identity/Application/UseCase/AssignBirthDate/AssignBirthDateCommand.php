<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\AssignBirthDate;

use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Command para definir a data de nascimento do usuário autenticado.
 */
final readonly class AssignBirthDateCommand
{
    public function __construct(
        private UserId $userId,
        private string $birthDate,
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function birthDate(): string
    {
        return $this->birthDate;
    }
}
