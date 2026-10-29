<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Exception\IdentityException;
use App\Identity\Domain\User\ValueObject\Weight;
use App\Shared\Domain\Error\ErrorKind;

/**
 * Exceção para peso inválido.
 */
final class InvalidWeightException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::UserWeightInvalid->value;
    }

    public function kind(): ErrorKind
    {
        return IdentityErrorCode::UserWeightInvalid->kind();
    }

    public function publicMessage(): string
    {
        return 'O peso informado é inválido.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'weightKg',
                'message' => sprintf(
                    'O peso deve estar entre %s e %s quilogramas.',
                    Weight::MIN_KILOGRAMS,
                    Weight::MAX_KILOGRAMS,
                ),
            ],
        ];
    }
}
