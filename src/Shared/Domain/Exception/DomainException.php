<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Exceção base para violações de regras de negócio do domínio.
 */
abstract class DomainException extends RuntimeException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
