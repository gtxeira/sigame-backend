<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * Value Object para status do onboarding do usuário.
 */
enum OnboardingStatus: string
{
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
}
