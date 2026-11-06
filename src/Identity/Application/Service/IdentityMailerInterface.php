<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Porta de envio de mensagens transacionais da identidade.
 */
interface IdentityMailerInterface
{
    public function sendEmailVerification(Email $to, string $token, Instant $expiresAt): void;

    public function sendPasswordReset(Email $to, string $token, Instant $expiresAt): void;
}
