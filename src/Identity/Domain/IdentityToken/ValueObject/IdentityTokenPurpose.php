<?php

declare(strict_types=1);

namespace App\Identity\Domain\IdentityToken\ValueObject;

/**
 * Finalidade de um token de uso único da identidade.
 */
enum IdentityTokenPurpose: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
}
