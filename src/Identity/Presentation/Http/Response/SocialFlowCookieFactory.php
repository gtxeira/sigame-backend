<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cookie HttpOnly que correlaciona o navegador ao fluxo OAuth server-side.
 *
 * O valor é o id opaco do fluxo; o state e o PKCE continuam no servidor.
 */
final readonly class SocialFlowCookieFactory
{
    private const string NAME = 'social_flow';
    private const string PATH = '/api/v1';

    public function __construct(
        #[Autowire(env: 'bool:REFRESH_COOKIE_SECURE')]
        private bool $secure,
        #[Autowire(env: 'REFRESH_COOKIE_SAMESITE')]
        private string $sameSite,
    ) {
    }

    public function create(string $flowId, Instant $expiresAt): Cookie
    {
        return Cookie::create(
            name: self::NAME,
            value: $flowId,
            expire: $expiresAt->value(),
            path: self::PATH,
            domain: null,
            secure: $this->secure,
            httpOnly: true,
            raw: false,
            sameSite: $this->sameSite,
        );
    }

    public function clear(): Cookie
    {
        return Cookie::create(
            name: self::NAME,
            value: null,
            expire: 1,
            path: self::PATH,
            domain: null,
            secure: $this->secure,
            httpOnly: true,
            raw: false,
            sameSite: $this->sameSite,
        );
    }

    public function name(): string
    {
        return self::NAME;
    }
}
