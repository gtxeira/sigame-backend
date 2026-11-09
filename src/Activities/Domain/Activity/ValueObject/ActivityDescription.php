<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Domain\Functional\ValidationResult;

final readonly class ActivityDescription
{
    public const int MAX_LENGTH = 500;

    private function __construct(private string $value)
    {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromString(string $value): ValidationResult
    {
        $normalized = trim($value);

        if ('' === $normalized || mb_strlen($normalized) > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ActivityErrors::descriptionInvalid(self::MAX_LENGTH),
            ]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    public function value(): string
    {
        return $this->value;
    }
}
