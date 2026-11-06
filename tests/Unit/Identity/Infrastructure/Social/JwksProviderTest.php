<?php

declare(strict_types=1);

use App\Identity\Infrastructure\Social\JwksProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function jwksProviderTestPayload(string $kid = 'kid-1'): string
{
    return json_encode([
        'keys' => [[
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $kid,
            'n' => 'n-value',
            'e' => 'AQAB',
        ]],
    ], JSON_THROW_ON_ERROR);
}

it('fetches the jwks once and serves the rest from cache', function () {
    $requests = 0;
    $http = new MockHttpClient(function () use (&$requests): MockResponse {
        ++$requests;

        return new MockResponse(jwksProviderTestPayload(), ['http_code' => 200]);
    });

    $provider = new JwksProvider($http, new ArrayAdapter(), 3600);

    $first = $provider->fetch('https://example.com/certs');
    $second = $provider->fetch('https://example.com/certs');

    expect($first['keys'][0]['kid'])->toBe('kid-1')
        ->and($second)->toBe($first)
        ->and($requests)->toBe(1);
});

it('forces a refresh when requested', function () {
    $requests = 0;
    $http = new MockHttpClient(function () use (&$requests): MockResponse {
        ++$requests;

        return new MockResponse(jwksProviderTestPayload('kid-'.$requests), ['http_code' => 200]);
    });

    $provider = new JwksProvider($http, new ArrayAdapter(), 3600);

    $provider->fetch('https://example.com/certs');
    $refreshed = $provider->fetch('https://example.com/certs', forceRefresh: true);

    expect($refreshed['keys'][0]['kid'])->toBe('kid-2')
        ->and($requests)->toBe(2);
});

it('throttles refresh calls to once per minute', function () {
    $requests = 0;
    $http = new MockHttpClient(function () use (&$requests): MockResponse {
        ++$requests;

        return new MockResponse(jwksProviderTestPayload('kid-'.$requests), ['http_code' => 200]);
    });

    $provider = new JwksProvider($http, new ArrayAdapter(), 3600);

    $provider->fetch('https://example.com/certs');
    $first = $provider->refresh('https://example.com/certs');
    $second = $provider->refresh('https://example.com/certs');

    expect($first['keys'][0]['kid'])->toBe('kid-2')
        ->and($second['keys'][0]['kid'])->toBe('kid-2')
        ->and($requests)->toBe(2);
});

it('rejects a payload without the keys member', function () {
    $http = new MockHttpClient(static fn (): MockResponse => new MockResponse(
        json_encode(['nope' => true], JSON_THROW_ON_ERROR),
        ['http_code' => 200],
    ));

    (new JwksProvider($http, new ArrayAdapter(), 3600))->fetch('https://example.com/certs');
})->throws(UnexpectedValueException::class);
