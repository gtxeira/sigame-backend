<?php

declare(strict_types=1);

namespace App\Shared\Domain\Functional;

use App\Shared\Domain\Error\DomainError;
use LogicException;

/**
 * Resultado genérico de operação de domínio.
 *
 * Representa sucesso ou falha esperada (regra de negócio).
 * Não deve ser usado para falhas técnicas ou violações de invariantes.
 *
 * @template T
 */
final readonly class Result
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
     * Cria um resultado de sucesso com o valor informado.
     *
     * @template U
     *
     * @param U $value
     *
     * @return self<U>
     */
    public static function success(mixed $value): self
    {
        return new self($value, []);
    }

    /**
     * Cria um resultado de falha com a lista de erros.
     *
     * @param list<DomainError> $errors
     *
     * @return self<never>
     */
    public static function failure(array $errors): self
    {
        return new self(null, $errors);
    }

    /**
     * Converte um ValidationResult em Result.
     *
     * Se o ValidationResult for válido, retorna Result::success com o valor.
     * Se for inválido, retorna Result::failure com os erros acumulados.
     *
     * @template U
     *
     * @param ValidationResult<U> $validation
     *
     * @return self<U>
     */
    public static function fromValidation(ValidationResult $validation): self
    {
        if ($validation->isValid()) {
            return self::success($validation->value());
        }

        return self::failure($validation->errors());
    }

    public function isSuccess(): bool
    {
        return [] === $this->errors;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess();
    }

    /**
     * @return T
     */
    public function value(): mixed
    {
        if ($this->isFailure()) {
            throw new LogicException('Não é possível obter o valor de um Result inválido.');
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

    /**
     * Transforma o valor de sucesso usando a função informada.
     *
     * Se o resultado for uma falha, retorna-o sem alteração.
     *
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return self<U>
     */
    /**
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return self<U>
     */
    public function map(callable $fn): self
    {
        if ($this->isFailure()) {
            return self::failure($this->errors);
        }

        return self::success($fn($this->value));
    }

    /**
     * Encadeia uma operação que retorna Result.
     *
     * Se o resultado atual for uma falha, retorna-o sem alteração.
     *
     * @template U
     *
     * @param callable(T): self<U> $fn
     *
     * @return self<U>
     */
    public function then(callable $fn): self
    {
        if ($this->isFailure()) {
            return self::failure($this->errors);
        }

        return $fn($this->value);
    }
}
