<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class UserCpfAlreadyExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'USER_CPF_ALREADY_EXISTS';
    }

    public function publicMessage(): string
    {
        return 'A user with this CPF already exists.';
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    public function errorDetails(): array
    {
        return [
            [
                'field' => 'cpf',
                'message' => 'This CPF already exists.',
            ],
        ];
    }
}
