<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Exception\IdentityException;
use App\Identity\Domain\User\ValueObject\Height;
use App\Shared\Domain\Error\ErrorKind;

/**
 * Exceção para altura inválida.
 */
final class InvalidHeightException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::UserHeightInvalid->value;
    }

    public function kind(): ErrorKind
    {
        return IdentityErrorCode::UserHeightInvalid->kind();
    }

    public function publicMessage(): string
    {
        return 'A altura informada é inválida.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'heightCm',
                'message' => sprintf(
                    'A altura deve estar entre %d e %d centímetros.',
                    Height::MIN_CENTIMETERS,
                    Height::MAX_CENTIMETERS,
                ),
            ],
        ];
    }
}
