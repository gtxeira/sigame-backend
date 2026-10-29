<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Exception\IdentityException;
use App\Shared\Domain\Error\ErrorKind;

/**
 * Exceção: modalidade principal não está entre as modalidades ativas.
 */
final class OnboardingInvalidPrimaryModalityException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::OnboardingInvalidPrimaryModality->value;
    }

    public function kind(): ErrorKind
    {
        return IdentityErrorCode::OnboardingInvalidPrimaryModality->kind();
    }

    public function publicMessage(): string
    {
        return 'A modalidade principal deve ser uma das modalidades selecionadas.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'primaryModality',
                'message' => 'A modalidade principal deve ser uma das modalidades ativas.',
            ],
        ];
    }
}
