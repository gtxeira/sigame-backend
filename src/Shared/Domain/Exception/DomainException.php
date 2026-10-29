<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use App\Shared\Domain\Error\ErrorKind;
use DomainException as NativeDomainException;
use Throwable;

/**
 * Exceção base para violações de regras de negócio do domínio.
 */
abstract class DomainException extends NativeDomainException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    abstract public function errorCode(): string;

    abstract public function kind(): ErrorKind;

    abstract public function publicMessage(): string;

    /**
     * @return list<array{field?: string, message?: string}>
     */
    public function errorDetails(): array
    {
        return [];
    }
}
