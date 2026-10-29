<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Error\ErrorKind;

/**
 * Exceção: onboarding já foi concluído.
 */
final class OnboardingAlreadyCompletedException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::OnboardingAlreadyCompleted->value;
    }

    public function kind(): ErrorKind
    {
        return IdentityErrorCode::OnboardingAlreadyCompleted->kind();
    }

    public function publicMessage(): string
    {
        return 'O onboarding já foi concluído.';
    }

    public function errorDetails(): array
    {
        return [];
    }
}
