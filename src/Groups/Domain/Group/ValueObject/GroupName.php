<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

use App\Groups\Domain\Error\GroupErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Nome de um grupo esportivo.
 */
final readonly class GroupName
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 80;

    private function __construct(
        private string $value,
    ) {
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
                GroupErrors::nameInvalid(self::MIN_LENGTH, self::MAX_LENGTH),
            ]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
