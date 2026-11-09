<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Domain\Functional\ValidationResult;

final readonly class EstimatedDistance
{
    public const int MIN_METERS = 1;
    public const int MAX_METERS = 1000000;

    private function __construct(private int $meters)
    {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromMeters(int $meters): ValidationResult
    {
        if ($meters < self::MIN_METERS || $meters > self::MAX_METERS) {
            return ValidationResult::invalid([
                ActivityErrors::estimatedDistanceInvalid(self::MIN_METERS, self::MAX_METERS),
            ]);
        }

        return ValidationResult::valid(new self($meters));
    }

    public function meters(): int
    {
        return $this->meters;
    }
}
