<?php

declare(strict_types=1);

namespace App\Identity\Domain\Otp\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Value Object tipado para identificadores de desafios OTP.
 */
final readonly class OtpChallengeId extends BaseId
{
}
