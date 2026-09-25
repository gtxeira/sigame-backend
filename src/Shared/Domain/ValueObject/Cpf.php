<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Value Object imutável para CPF (Cadastro de Pessoas Físicas).
 *
 * Valida o formato e os dígitos verificadores na criação.
 * Nunca permite a existência de um CPF inválido.
 */
final readonly class Cpf
{
    private function __construct(
        private string $value,
    ) {
    }

    /**
     * Cria um CPF a partir de uma string, validando formato e dígitos verificadores.
     *
     * @return ValidationResult<self>
     */
    public static function fromString(string $cpf): ValidationResult
    {
        $normalized = preg_replace('/\D/', '', $cpf);

        if (11 !== strlen($normalized)) {
            return ValidationResult::invalid([
                new DomainError(
                    code: 'CPF_INVALID',
                    message: 'O CPF deve conter exatamente 11 dígitos.',
                    field: 'cpf',
                ),
            ]);
        }

        if (preg_match('/^(\d)\1{10}$/', $normalized)) {
            return ValidationResult::invalid([
                new DomainError(
                    code: 'CPF_INVALID',
                    message: 'O CPF não pode conter todos os dígitos iguais.',
                    field: 'cpf',
                ),
            ]);
        }

        if (!self::isValidCheckDigits($normalized)) {
            return ValidationResult::invalid([
                new DomainError(
                    code: 'CPF_INVALID',
                    message: 'O CPF possui dígitos verificadores inválidos.',
                    field: 'cpf',
                ),
            ]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function formatted(): string
    {
        return sprintf(
            '%s.%s.%s-%s',
            substr($this->value, 0, 3),
            substr($this->value, 3, 3),
            substr($this->value, 6, 3),
            substr($this->value, 9, 2),
        );
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function isValidCheckDigits(string $cpf): bool
    {
        // Primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; ++$i) {
            $sum += (int) $cpf[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $firstDigit = $remainder < 2 ? 0 : 11 - $remainder;

        if ((int) $cpf[9] !== $firstDigit) {
            return false;
        }

        // Segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; ++$i) {
            $sum += (int) $cpf[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $secondDigit = $remainder < 2 ? 0 : 11 - $remainder;

        return (int) $cpf[10] === $secondDigit;
    }
}
