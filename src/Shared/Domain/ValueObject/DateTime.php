<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDateTimeException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Value Object imutável para datetimes.
 *
 * Wraps DateTimeImmutable para manter consistência no domínio.
 */
final readonly class DateTime
{
    private function __construct(
        private DateTimeImmutable $value,
    ) {
    }

    public static function now(): self
    {
        return new self(new DateTimeImmutable());
    }

    public static function fromString(string $datetime, string $timezone = 'UTC'): self
    {
        $dt = DateTimeImmutable::createFromFormat(
            DateTimeInterface::RFC3339_EXTENDED,
            $datetime,
            new DateTimeZone($timezone),
        );

        if (false === $dt) {
            throw new InvalidDateTimeException();
        }

        return new self($dt);
    }

    public static function fromTimestamp(int $timestamp): self
    {
        $dt = DateTimeImmutable::createFromFormat('U', (string) $timestamp);

        if (false === $dt) {
            throw new InvalidDateTimeException();
        }

        return new self($dt);
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function timestamp(): int
    {
        return $this->value->getTimestamp();
    }

    public function addDays(int $days): self
    {
        return new self($this->value->modify("+{$days} days"));
    }

    public function addHours(int $hours): self
    {
        return new self($this->value->modify("+{$hours} hours"));
    }

    public function addMinutes(int $minutes): self
    {
        return new self($this->value->modify("+{$minutes} minutes"));
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value == $other->value;
    }

    public function format(string $format = DateTimeInterface::RFC3339_EXTENDED): string
    {
        return $this->value->format($format);
    }

    public function toIsoString(): string
    {
        return $this->value->format(DateTimeInterface::RFC3339_EXTENDED);
    }

    public function __toString(): string
    {
        return $this->toIsoString();
    }
}
