<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Valida a origem de requisições autenticadas por cookie.
 *
 * Com `SameSite=None`, o navegador envia o cookie em requisições cross-site.
 * Exigir que a origem esteja na mesma allowlist do CORS fecha a janela de
 * CSRF sem afetar chamadas server-to-server (sem `Origin`).
 */
final readonly class CookieOriginGuard
{
    public function __construct(
        #[Autowire(env: 'CORS_ALLOW_ORIGINS')]
        private string $allowedOrigins,
    ) {
    }

    public function assertAllowed(Request $request): void
    {
        $origin = $request->headers->get('Origin');

        if (null === $origin || '' === $origin) {
            return;
        }

        if (preg_match('{'.$this->allowedOrigins.'}i', $origin) === 1) {
            return;
        }

        throw new CookieOriginNotAllowedException();
    }
}
