<?php

declare(strict_types=1);

use App\Shared\Presentation\Http\Security\CookieOriginGuard;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

function cookieOriginRequest(?string $origin): Request
{
    $request = Request::create('/api/v1/auth/refresh', 'POST');

    if (null !== $origin) {
        $request->headers->set('Origin', $origin);
    }

    return $request;
}

it('allows requests without an origin', function () {
    $guard = new CookieOriginGuard('^http://localhost:3000$');

    expect(static fn () => $guard->assertAllowed(cookieOriginRequest(null)))
        ->not->toThrow(AccessDeniedHttpException::class);
});

it('allows an empty origin', function () {
    $guard = new CookieOriginGuard('^http://localhost:3000$');

    expect(static fn () => $guard->assertAllowed(cookieOriginRequest('')))
        ->not->toThrow(AccessDeniedHttpException::class);
});

it('allows an origin from the allowlist', function () {
    $guard = new CookieOriginGuard('^https?://(app\.example\.com|localhost:3000)$');

    expect(static fn () => $guard->assertAllowed(cookieOriginRequest('https://app.example.com')))
        ->not->toThrow(AccessDeniedHttpException::class);
});

it('rejects an origin outside the allowlist', function () {
    $guard = new CookieOriginGuard('^http://localhost:3000$');

    $guard->assertAllowed(cookieOriginRequest('https://evil.example.com'));
})->throws(AccessDeniedHttpException::class);

it('rejects a suffix attack against an anchored allowlist', function () {
    $guard = new CookieOriginGuard('^https://sigame\.com\.br$');

    $guard->assertAllowed(cookieOriginRequest('https://sigame.com.br.evil.example.com'));
})->throws(AccessDeniedHttpException::class);

it('matches the origin case-insensitively', function () {
    $guard = new CookieOriginGuard('^https://app\.example\.com$');

    expect(static fn () => $guard->assertAllowed(cookieOriginRequest('https://APP.EXAMPLE.COM')))
        ->not->toThrow(AccessDeniedHttpException::class);
});
