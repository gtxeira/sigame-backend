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
abstract readonly class BaseId
{
    final private function __construct(
        private string $value,
    ) {
    }

    public static function generate(): static
    {
        return new static(SymfonyUuid::v7()->toRfc4122());
    }

    /**
     * @throws InvalidUuidException
     */
    public static function fromString(string $value): static
    {
        if (!SymfonyUuid::isValid($value)) {
            throw new InvalidUuidException();
        }

        $uuid = SymfonyUuid::fromString($value);

        return new static($uuid->toRfc4122());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this::class === $other::class && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
