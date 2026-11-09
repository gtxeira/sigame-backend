<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * Natureza semântica de um erro de domínio.
 *
 * Não conhece HTTP: a presentation decide o status a partir do kind.
 * `Authentication` indica credencial ausente/inválida; `Forbidden` indica que a
 * conta não pode executar a operação; `NotFound` indica recurso inexistente;
 * `Validation` indica entrada inválida; `Conflict` indica que a operação
 * conflita com o estado atual do recurso.
 */
enum ErrorKind
{
    case Authentication;
    case Forbidden;
    case NotFound;
    case Validation;
    case Conflict;
}
