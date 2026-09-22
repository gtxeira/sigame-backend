<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class EmailAlreadyExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'USER_EMAIL_ALREADY_EXISTS';
    }

    public function publicMessage(): string
    {
        return 'A user with this email already exists.';
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    public function errorDetails(): array
    {
        return [
            [
                'field' => 'email',
                'message' => 'This email already exists.',
            ],
        ];
    }
}
