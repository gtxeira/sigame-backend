<?php

declare(strict_types=1);

namespace App\Identity\Domain\RefreshToken\ValueObject;

use InvalidArgumentException;

/**
 * Hash SHA-256 de um refresh token opaco.
 *
 * O valor bruto nunca é persistido: o banco guarda apenas este hash.
 */
final readonly class TokenHash
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromRaw(string $rawToken): self
    {
        return new self(hash('sha256', $rawToken));
    }

    public static function fromHash(string $hash): self
    {
        $normalized = strtolower(trim($hash));

        if (1 !== preg_match('/^[a-f0-9]{64}$/', $normalized)) {
            throw new InvalidArgumentException('O hash do refresh token deve ter 64 caracteres hexadecimais.');
        }

        return new self($normalized);
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
