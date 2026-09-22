<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

/**
 * Exceção: nenhuma modalidade selecionada no onboarding.
 */
final class OnboardingNoModalitySelectedException extends IdentityException
{
    public function errorCode(): string
    {
        return 'ONBOARDING_NO_MODALITY_SELECTED';
    }

    public function publicMessage(): string
    {
        return 'At least one modality must be selected.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'modalities',
                'message' => 'At least one modality is required to complete onboarding.',
            ],
        ];
    }
}
