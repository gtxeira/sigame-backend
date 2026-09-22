<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

/**
 * Exceção: onboarding já foi concluído.
 */
final class OnboardingAlreadyCompletedException extends IdentityException
{
    public function errorCode(): string
    {
        return 'ONBOARDING_ALREADY_COMPLETED';
    }

    public function publicMessage(): string
    {
        return 'Onboarding has already been completed.';
    }

    public function errorDetails(): array
    {
        return [];
    }
}
