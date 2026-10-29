<?php

declare(strict_types=1);

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\ErrorKind;

/**
 * Catálogo de códigos de erro do módulo Identity.
 *
 * O valor de cada caso é contrato público com o cliente da API:
 * alterá-lo é breaking change; renomear o caso não é.
 */
enum IdentityErrorCode: string
{
    case UserCpfAlreadyExists = 'USER_CPF_ALREADY_EXISTS';
    case EmailAlreadyExists = 'EMAIL_ALREADY_EXISTS';
    case UserLevelInvalid = 'USER_LEVEL_INVALID';
    case OnboardingAlreadyCompleted = 'ONBOARDING_ALREADY_COMPLETED';
    case OnboardingInvalidPrimaryModality = 'ONBOARDING_INVALID_PRIMARY_MODALITY';
    case OnboardingNoModalitySelected = 'ONBOARDING_NO_MODALITY_SELECTED';
    case UserAlreadyActive = 'USER_ALREADY_ACTIVE';
    case UserAlreadySuspended = 'USER_ALREADY_SUSPENDED';

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::UserCpfAlreadyExists,
            self::EmailAlreadyExists,
            self::OnboardingAlreadyCompleted,
            self::UserAlreadyActive,
            self::UserAlreadySuspended => ErrorKind::Conflict,

            self::UserLevelInvalid,
            self::OnboardingInvalidPrimaryModality,
            self::OnboardingNoModalitySelected => ErrorKind::Validation,
        };
    }
}
