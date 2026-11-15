<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Service\OtpCodeGeneratorInterface;
use App\Identity\Domain\Otp\ValueObject\OtpCode;

/**
 * Gera códigos OTP numéricos de seis dígitos com entropia criptográfica.
 */
final readonly class RandomOtpCodeGenerator implements OtpCodeGeneratorInterface
{
    public function generate(): OtpCode
    {
        return OtpCode::fromGenerated(str_pad(
            (string) random_int(0, 999_999),
            OtpCode::LENGTH,
            '0',
            STR_PAD_LEFT,
        ));
    }
}
