<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\EmailVerification;

/**
 * Resultado da tentativa de emitir um token de verificação de e-mail.
 */
enum EmailVerificationRequestOutcome
{
    case Queued;
    case AlreadyVerified;
}
