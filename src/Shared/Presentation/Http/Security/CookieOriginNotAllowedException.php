<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Security;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Origem não permitida em operações autenticadas por cookie.
 *
 * Permite que o listener HTTP diferencie um bloqueio de CSRF/origem de um
 * 403 genérico de autorização.
 */
final class CookieOriginNotAllowedException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('Origem não permitida para esta operação.');
    }
}
