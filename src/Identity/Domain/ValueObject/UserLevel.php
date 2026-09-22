<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidUserLevelException;

/**
 * Value Object imutável para nível do usuário (1–10).
 */
final readonly class UserLevel
{
    private const MIN_LEVEL = 1;
    private const MAX_LEVEL = 10;

    private function __construct(
        private int $value,
    ) {
    }

    public static function initial(): self
    {
        return new self(self::MIN_LEVEL);
    }

    public static function fromInt(int $level): self
    {
        if ($level < self::MIN_LEVEL || $level > self::MAX_LEVEL) {
            throw new InvalidUserLevelException();
        }

        return new self($level);
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
