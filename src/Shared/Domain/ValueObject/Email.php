<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Value Object imutável para endereços de e-mail.
 *
 * Valida o formato na criação e garante imutabilidade.
 * Nunca permite a existência de um e-mail inválido.
 */
final readonly class Email
{
    public const int MAX_LENGTH = 255;

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

        if ('' === $normalized) {
            return ValidationResult::invalid([
                ValidationErrors::fieldRequired('email', 'O e-mail é obrigatório.'),
            ]);
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooLong('email', sprintf('O e-mail deve ter no máximo %d caracteres.', self::MAX_LENGTH)),
            ]);
        }

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::invalid([
                ValidationErrors::invalidFormat('email', 'O e-mail informado é inválido.'),
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
