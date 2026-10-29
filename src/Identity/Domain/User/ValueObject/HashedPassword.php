<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use App\Identity\Domain\User\Exception\InvalidHashedPasswordException;

/**
 * Value Object imutável para senhas hasheadas.
 *
 * Nunca armazena a senha em texto puro.
 */
final readonly class HashedPassword
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromHash(string $hash): self
    {
        if (empty($hash)) {
            throw new InvalidHashedPasswordException();
        }

        return new self($hash);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
