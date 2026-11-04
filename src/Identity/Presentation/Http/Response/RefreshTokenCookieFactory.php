<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cria e limpa o cookie do refresh token.
 *
 * O caminho é restrito ao módulo de autenticação; o valor é inacessível ao
 * JavaScript por ser HttpOnly.
 */
final readonly class RefreshTokenCookieFactory
{
    public function __construct(
        #[Autowire('%env(REFRESH_COOKIE_NAME)%')]
        private string $name,
        #[Autowire('%env(bool:REFRESH_COOKIE_SECURE)%')]
        private bool $secure,
        #[Autowire('%env(REFRESH_COOKIE_SAMESITE)%')]
        private string $sameSite,
        #[Autowire('%env(REFRESH_COOKIE_PATH)%')]
        private string $path = '/api/v1/auth',
    ) {
    }

    public function create(string $rawToken, Instant $expiresAt): Cookie
    {
        return Cookie::create(
            name: $this->name,
            value: $rawToken,
            expire: $expiresAt->value(),
            path: $this->path,
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
            name: $this->name,
            value: null,
            expire: 1,
            path: $this->path,
            domain: null,
            secure: $this->secure,
            httpOnly: true,
            raw: false,
            sameSite: $this->sameSite,
        );
    }

    public function name(): string
    {
        return $this->name;
    }
}
