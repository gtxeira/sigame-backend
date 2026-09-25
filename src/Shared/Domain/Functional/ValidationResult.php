<?php

declare(strict_types=1);

namespace App\Shared\Domain\Functional;

use App\Shared\Domain\Error\DomainError;
use LogicException;

/**
 * Resultado de validação de entrada.
 *
 * Acumula múltiplos erros de validação independentes.
 * Usado na criação de Value Objects para reportar falhas antes de construir o objeto.
 *
 * @template T
 */
final readonly class ValidationResult
{
    /**
     * @param T|null            $value
     * @param list<DomainError> $errors
     */
    private function __construct(
        private mixed $value,
        private array $errors,
    ) {
    }

    /**
     * Cria um resultado válido com o valor informado.
     *
     * @template U
     *
     * @param U $value
     *
     * @return self<U>
     */
    public static function valid(mixed $value): self
    {
        return new self($value, []);
    }

    /**
     * Cria um resultado inválido com a lista de erros.
     *
     * @param list<DomainError> $errors
     *
     * @return self<never>
     */
    public static function invalid(array $errors): self
    {
        return new self(null, $errors);
    }

    /**
     * Combina múltiplos resultados de validação independentes.
     *
     * Se todos forem válidos, retorna um resultado válido contendo os valores.
     * Se algum for inválido, acumula todos os erros em um único resultado inválido.
     *
     * @param self<mixed> ...$results
     *
     * @return self<list<mixed>>
     */
    public static function combine(self ...$results): self
    {
        $allErrors = [];
        $allValues = [];

        foreach ($results as $result) {
            if ($result->isInvalid()) {
                $allErrors = array_merge($allErrors, $result->errors());
            } else {
                $allValues[] = $result->value();
            }
        }

        if ([] !== $allErrors) {
            return self::invalid($allErrors);
        }

        return self::valid($allValues);
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    public function isInvalid(): bool
    {
        return !$this->isValid();
    }

    /**
     * @return T
     */
    public function value(): mixed
    {
        if ($this->isInvalid()) {
            throw new LogicException('Não é possível obter o valor de um ValidationResult inválido.');
        }

        return $this->value;
    }

    /**
     * @return list<DomainError>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
