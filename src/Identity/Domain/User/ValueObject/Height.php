<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Value Object imutável para altura, expressa em centímetros.
 */
final readonly class Height
{
    public const int MIN_CENTIMETERS = 50;
    public const int MAX_CENTIMETERS = 250;

    private function __construct(
        private int $centimeters,
    ) {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromCentimeters(int $centimeters): ValidationResult
    {
        if ($centimeters < self::MIN_CENTIMETERS || $centimeters > self::MAX_CENTIMETERS) {
            return ValidationResult::invalid([
                ValidationErrors::numberOutOfRange('heightCm', sprintf('A altura deve estar entre %d e %d centímetros.', self::MIN_CENTIMETERS, self::MAX_CENTIMETERS)),
            ]);
        }

        return ValidationResult::valid(new self($centimeters));
    }

    public function centimeters(): int
    {
        return $this->centimeters;
    }

    public function meters(): float
    {
        return $this->centimeters / 100;
    }

    public function equals(self $other): bool
    {
        return $this->centimeters === $other->centimeters;
    }
}
