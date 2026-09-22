<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Exceção para argumentos inválidos nas regras de negócio.
 *
 * Diferente da \InvalidArgumentException do SPL, esta é uma DomainException.
 */
abstract class InvalidArgumentException extends DomainException
{
}
