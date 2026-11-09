<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

use App\Groups\Domain\Error\GroupErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Descrição opcional de um grupo.
 */
final readonly class GroupDescription
{
    public const int MAX_LENGTH = 500;

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

        if ('' === $normalized) {
            return ValidationResult::invalid([
                GroupErrors::descriptionInvalid(self::MAX_LENGTH),
            ]);
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                GroupErrors::descriptionInvalid(self::MAX_LENGTH),
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
