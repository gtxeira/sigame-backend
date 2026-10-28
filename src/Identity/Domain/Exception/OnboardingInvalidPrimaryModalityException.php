<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;

/**
 * Exceção: modalidade principal não está entre as modalidades ativas.
 */
final class OnboardingInvalidPrimaryModalityException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::OnboardingInvalidPrimaryModality->value;
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
