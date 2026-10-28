<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Error\IdentityErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Value Object imutável para nível do usuário.
 */
final readonly class UserLevel
{
    public const int MIN_LEVEL = 1;
    public const int MAX_LEVEL = 10;

    private function __construct(
        private int $value,
    ) {
    }

    public static function initial(): self
    {
        return new self(self::MIN_LEVEL);
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromInt(int $level): ValidationResult
    {
        if ($level < self::MIN_LEVEL) {
            return ValidationResult::invalid([
                IdentityErrors::userLevelBelowMinimum(self::MIN_LEVEL),
            ]);
        }

        if ($level > self::MAX_LEVEL) {
            return ValidationResult::invalid([
                IdentityErrors::userLevelAboveMaximum(self::MAX_LEVEL),
            ]);
        }

        return ValidationResult::valid(new self($level));
    }

    public function value(): int
    {
        return $this->value;
    }

    public function next(): self
    {
        if ($this->value >= self::MAX_LEVEL) {
            return $this;
        }

        return new self($this->value + 1);
    }

    public function previous(): self
    {
        if ($this->value <= self::MIN_LEVEL) {
            return $this;
        }

        return new self($this->value - 1);
    }

    public function isAtLeast(int $level): bool
    {
        return $this->value >= $level;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
