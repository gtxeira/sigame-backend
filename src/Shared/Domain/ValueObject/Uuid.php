<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidUuidException;
use Symfony\Component\Uid\Uuid as SymfonyUuid;

/**
 * Value Object imutável para identificadores únicos (UUID v7).
 *
 * Wraps o Symfony Uid para manter o Domain puro de dependências de framework.
 */
final readonly class Uuid
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(SymfonyUuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        if (!SymfonyUuid::isValid($value)) {
            throw new InvalidUuidException();
        }

        $uuid = SymfonyUuid::fromString($value);

        return new self($uuid->toRfc4122());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
