<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Domain\Otp\ValueObject\OtpCode;

/**
 * Porta de geração de códigos OTP.
 */
interface OtpCodeGeneratorInterface
{
    public function generate(): OtpCode;
}
