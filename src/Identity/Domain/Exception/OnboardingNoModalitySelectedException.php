<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;

/**
 * Exceção: nenhuma modalidade selecionada no onboarding.
 */
final class OnboardingNoModalitySelectedException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::OnboardingNoModalitySelected->value;
    }

    public function publicMessage(): string
    {
        return 'Pelo menos uma modalidade deve ser selecionada.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'modalities',
                'message' => 'É necessário selecionar ao menos uma modalidade.',
            ],
        ];
    }
}
