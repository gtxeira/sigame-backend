<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Domain\Functional\ValidationResult;

final readonly class MeetingPoint
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 255;

    private function __construct(private string $value)
    {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromString(string $value): ValidationResult
    {
        $normalized = trim($value);
        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ActivityErrors::meetingPointInvalid(self::MIN_LENGTH, self::MAX_LENGTH),
            ]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    public function value(): string
    {
        return $this->value;
    }
}
