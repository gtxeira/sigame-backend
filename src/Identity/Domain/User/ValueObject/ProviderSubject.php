<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use InvalidArgumentException;

/**
 * Identificador do usuário no provedor de identidade.
 *
 * Para identidades locais é o e-mail normalizado; para provedores sociais é o
 * `sub` devolvido pelo provedor.
 */
final readonly class ProviderSubject
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new InvalidArgumentException('O identificador do provedor não pode ser vazio.');
        }

        if (mb_strlen($normalized) > 255) {
            throw new InvalidArgumentException('O identificador do provedor deve ter no máximo 255 caracteres.');
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
