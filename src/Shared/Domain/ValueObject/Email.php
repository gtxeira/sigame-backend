<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Error\SharedErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Value Object imutável para endereços de e-mail.
 *
 * Valida o formato na criação e garante imutabilidade.
 * Nunca permite a existência de um e-mail inválido.
 */
final readonly class Email
{
    private function __construct(
        private string $value,
    ) {
    }

    /**
     * Cria um e-mail a partir de uma string, validando o formato.
     *
     * @return ValidationResult<self>
     */
    public static function fromString(string $email): ValidationResult
    {
        $normalized = strtolower(trim($email));

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::invalid([
                SharedErrors::emailInvalid(),
            ]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function domain(): string
    {
        return substr($this->value, strrpos($this->value, '@') + 1);
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
