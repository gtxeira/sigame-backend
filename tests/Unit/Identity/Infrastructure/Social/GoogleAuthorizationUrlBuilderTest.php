<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Infrastructure\Social\GoogleAuthorizationUrlBuilder;

function googleUrlBuilder(
    string $clientId = 'client-id',
    string $webRedirectUri = 'https://app.example.com/auth/google/callback',
    ?string $devRedirectUri = 'https://api.example.com/dev/google-oauth',
    string $environment = 'dev',
): GoogleAuthorizationUrlBuilder {
    return new GoogleAuthorizationUrlBuilder(
        clientId: $clientId,
        webRedirectUri: $webRedirectUri,
        devRedirectUri: $devRedirectUri,
        environment: $environment,
    );
}

it('resolves the web redirect uri on the server', function () {
    expect(googleUrlBuilder()->redirectUri(IdentityProvider::GOOGLE, 'web'))
        ->toBe('https://app.example.com/auth/google/callback');
});

it('resolves the dev redirect uri only outside production', function () {
    expect(googleUrlBuilder()->redirectUri(IdentityProvider::GOOGLE, 'dev'))
        ->toBe('https://api.example.com/dev/google-oauth')
        ->and(googleUrlBuilder(environment: 'prod')->redirectUri(IdentityProvider::GOOGLE, 'dev'))
        ->toBeNull();
});

it('rejects dev when no dev redirect uri is configured', function (?string $devRedirectUri) {
    expect(googleUrlBuilder(devRedirectUri: $devRedirectUri)->redirectUri(IdentityProvider::GOOGLE, 'dev'))
        ->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
]);

it('rejects an empty web redirect uri', function () {
    expect(googleUrlBuilder(webRedirectUri: '')->redirectUri(IdentityProvider::GOOGLE, 'web'))
        ->toBeNull();
});

it('rejects an unknown client', function () {
    expect(googleUrlBuilder()->redirectUri(IdentityProvider::GOOGLE, 'mobile'))->toBeNull();
});

it('rejects a provider without a configured redirect uri', function () {
    expect(googleUrlBuilder()->redirectUri(IdentityProvider::LOCAL, 'web'))->toBeNull();
});

it('builds the google authorization url with state and pkce', function () {
    $url = googleUrlBuilder()->authorizationUrl(
        provider: IdentityProvider::GOOGLE,
        redirectUri: 'https://app.example.com/auth/google/callback',
        state: 'state-value',
        codeChallenge: 'challenge-value',
    );

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'client_id' => 'client-id',
        'redirect_uri' => 'https://app.example.com/auth/google/callback',
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => 'state-value',
        'code_challenge' => 'challenge-value',
        'code_challenge_method' => 'S256',
    ]);
});

it('encodes reserved characters in the authorization url', function () {
    $url = googleUrlBuilder(webRedirectUri: 'https://app.example.com/callback?x=1')->authorizationUrl(
        provider: IdentityProvider::GOOGLE,
        redirectUri: 'https://app.example.com/callback?x=1',
        state: 'a b',
        codeChallenge: 'c/d+e',
    );

    expect($url)
        ->toContain('redirect_uri=https%3A%2F%2Fapp.example.com%2Fcallback%3Fx%3D1')
        ->toContain('state=a%20b')
        ->toContain('code_challenge=c%2Fd%2Be');
});

it('throws when building a url for another provider', function () {
    googleUrlBuilder()->authorizationUrl(
        provider: IdentityProvider::LOCAL,
        redirectUri: 'https://app.example.com/callback',
        state: 'state',
        codeChallenge: 'challenge',
    );
})->throws(InvalidArgumentException::class);
