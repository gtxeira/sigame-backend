<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

/**
 * Exceção: modalidade principal não está entre as modalidades ativas.
 */
final class OnboardingInvalidPrimaryModalityException extends IdentityException
{
    public function errorCode(): string
    {
        return 'ONBOARDING_INVALID_PRIMARY_MODALITY';
    }

    public function publicMessage(): string
    {
        return 'The primary modality must be one of the selected modalities.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'primaryModality',
                'message' => 'Primary modality must be one of the active modalities.',
            ],
        ];
    }
}
