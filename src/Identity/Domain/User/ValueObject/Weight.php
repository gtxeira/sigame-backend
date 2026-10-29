<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use App\Identity\Domain\Error\IdentityErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Value Object imutável para peso, expresso em quilogramas.
 */
final readonly class Weight
{
    public const float MIN_KILOGRAMS = 20.0;
    public const float MAX_KILOGRAMS = 300.0;

    private function __construct(
        private float $kilograms,
    ) {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromKilograms(float $kilograms): ValidationResult
    {
        if ($kilograms < self::MIN_KILOGRAMS || $kilograms > self::MAX_KILOGRAMS) {
            return ValidationResult::invalid([
                IdentityErrors::userWeightInvalid(self::MIN_KILOGRAMS, self::MAX_KILOGRAMS),
            ]);
        }

        return ValidationResult::valid(new self($kilograms));
    }

    public function kilograms(): float
    {
        return $this->kilograms;
    }

    public function equals(self $other): bool
    {
        return $this->kilograms === $other->kilograms;
    }
}
