<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Exceção para argumentos inválidos nas regras de negócio.
 *
 * Diferente da \InvalidArgumentException do SPL, esta é uma DomainException.
 */
class InvalidArgumentException extends DomainException
{
    public static function fromField(string $field, string $message): self
    {
        return new self(sprintf("Invalid value for '%s': %s", $field, $message));
    }
}
