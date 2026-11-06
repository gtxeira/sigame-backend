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
    case SocialAccountLinkRequired = 'SOCIAL_ACCOUNT_LINK_REQUIRED';
    case SocialIdentityAlreadyLinked = 'SOCIAL_IDENTITY_ALREADY_LINKED';
    case SocialLinkEmailMismatch = 'SOCIAL_LINK_EMAIL_MISMATCH';
    case SocialLinkTokenInvalid = 'SOCIAL_LINK_TOKEN_INVALID';
    case SocialFlowInvalid = 'SOCIAL_FLOW_INVALID';
    case SocialClientUnsupported = 'SOCIAL_CLIENT_UNSUPPORTED';
    case EmailVerificationTokenInvalid = 'EMAIL_VERIFICATION_TOKEN_INVALID';
    case EmailVerificationTokenExpired = 'EMAIL_VERIFICATION_TOKEN_EXPIRED';
    case PasswordResetTokenInvalid = 'PASSWORD_RESET_TOKEN_INVALID';
    case PasswordResetTokenExpired = 'PASSWORD_RESET_TOKEN_EXPIRED';
    case PasswordCurrentInvalid = 'PASSWORD_CURRENT_INVALID';

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::UserCpfAlreadyExists,
            self::UserCpfAlreadySet,
            self::EmailAlreadyExists,
            self::OnboardingAlreadyCompleted,
            self::UserAlreadyActive,
            self::UserAlreadySuspended,
            self::RefreshInProgress,
            self::SocialAccountLinkRequired,
            self::SocialIdentityAlreadyLinked,
            self::SocialLinkEmailMismatch => ErrorKind::Conflict,

            self::UserLevelInvalid,
            self::UserHeightInvalid,
            self::UserWeightInvalid,
            self::InvalidHashedPassword,
            self::OnboardingInvalidPrimaryModality,
            self::OnboardingNoModalitySelected,
            self::SocialProviderUnsupported,
            self::SocialEmailNotVerified,
            self::SocialEmailMissing,
            self::SocialLinkTokenInvalid,
            self::SocialFlowInvalid,
            self::SocialClientUnsupported,
            self::EmailVerificationTokenInvalid,
            self::EmailVerificationTokenExpired,
            self::PasswordResetTokenInvalid,
            self::PasswordResetTokenExpired,
            self::PasswordCurrentInvalid => ErrorKind::Validation,

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
