<?php

declare(strict_types=1);

namespace App\Identity\Domain\Otp\ValueObject;

/**
 * Finalidade de um código OTP da identidade.
 */
enum OtpPurpose: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
}
