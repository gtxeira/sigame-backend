<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Exceção base para violações de regras de negócio do módulo Identity.
 */
abstract class IdentityException extends DomainException
{
}
