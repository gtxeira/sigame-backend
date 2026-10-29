<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * Natureza semântica de um erro de domínio.
 *
 * Não conhece HTTP: a presentation decide o status a partir do kind.
 * `Validation` indica entrada inválida; `Conflict` indica que a
 * operação conflita com o estado atual do recurso.
 */
enum ErrorKind
{
    case Validation;
    case Conflict;
}
