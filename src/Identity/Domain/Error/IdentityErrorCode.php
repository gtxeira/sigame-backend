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
    case UserCpfAlreadySet = 'USER_CPF_ALREADY_SET';
    case EmailAlreadyExists = 'EMAIL_ALREADY_EXISTS';
    case InvalidHashedPassword = 'INVALID_HASHED_PASSWORD';
    case UserLevelInvalid = 'USER_LEVEL_INVALID';
    case UserHeightInvalid = 'USER_HEIGHT_INVALID';
    case UserWeightInvalid = 'USER_WEIGHT_INVALID';
    case OnboardingAlreadyCompleted = 'ONBOARDING_ALREADY_COMPLETED';
    case OnboardingInvalidPrimaryModality = 'ONBOARDING_INVALID_PRIMARY_MODALITY';
    case OnboardingNoModalitySelected = 'ONBOARDING_NO_MODALITY_SELECTED';
    case UserAlreadyActive = 'USER_ALREADY_ACTIVE';
    case UserAlreadySuspended = 'USER_ALREADY_SUSPENDED';

    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case AccountSuspended = 'ACCOUNT_SUSPENDED';
    case RefreshTokenInvalid = 'REFRESH_TOKEN_INVALID';
    case RefreshTokenExpired = 'REFRESH_TOKEN_EXPIRED';
    case RefreshTokenReused = 'REFRESH_TOKEN_REUSED';
    case RefreshTokenAlreadyRotated = 'REFRESH_TOKEN_ALREADY_ROTATED';
    case RefreshInProgress = 'REFRESH_IN_PROGRESS';
    case SocialProviderUnsupported = 'SOCIAL_PROVIDER_UNSUPPORTED';
    case SocialAuthenticationFailed = 'SOCIAL_AUTHENTICATION_FAILED';
    case SocialEmailNotVerified = 'SOCIAL_EMAIL_NOT_VERIFIED';
    case SocialEmailMissing = 'SOCIAL_EMAIL_MISSING';

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::UserCpfAlreadyExists,
            self::UserCpfAlreadySet,
            self::EmailAlreadyExists,
            self::OnboardingAlreadyCompleted,
            self::UserAlreadyActive,
            self::UserAlreadySuspended,
            self::RefreshInProgress => ErrorKind::Conflict,

            self::UserLevelInvalid,
            self::UserHeightInvalid,
            self::UserWeightInvalid,
            self::InvalidHashedPassword,
            self::OnboardingInvalidPrimaryModality,
            self::OnboardingNoModalitySelected,
            self::SocialProviderUnsupported,
            self::SocialEmailNotVerified,
            self::SocialEmailMissing => ErrorKind::Validation,

            self::InvalidCredentials,
            self::RefreshTokenInvalid,
            self::RefreshTokenExpired,
            self::RefreshTokenReused,
            self::RefreshTokenAlreadyRotated,
            self::SocialAuthenticationFailed => ErrorKind::Authentication,

            self::AccountSuspended => ErrorKind::Forbidden,
        };
    }
}
