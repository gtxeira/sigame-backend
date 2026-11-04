<?php

declare(strict_types=1);

use App\Identity\Application\Exception\SocialAuthenticationException;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Infrastructure\Social\GoogleIdentityProvider;
use App\Identity\Infrastructure\Social\IdTokenVerifierInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function googleProvider(MockHttpClient $httpClient, IdTokenVerifierInterface $verifier): GoogleIdentityProvider
{
    return new GoogleIdentityProvider(
        httpClient: $httpClient,
        idTokenVerifier: $verifier,
        clientId: 'client-id',
        clientSecret: 'client-secret',
        redirectUris: 'http://localhost:3000/auth/google/callback',
    );
}

it('exchanges the authorization code and maps the Google identity', function () {
    $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(
        json_encode(['id_token' => 'id-token'], JSON_THROW_ON_ERROR),
        ['http_code' => 200],
    ));

    $verifier = Mockery::mock(IdTokenVerifierInterface::class);
    $verifier->shouldReceive('verify')
        ->once()
        ->with(
            'id-token',
            'https://www.googleapis.com/oauth2/v3/certs',
            'client-id',
            ['https://accounts.google.com', 'accounts.google.com'],
        )
        ->andReturn([
            'sub' => 'google-sub',
            'email' => 'user@example.com',
            'email_verified' => true,
            'name' => 'Google User',
            'picture' => 'https://example.com/photo.jpg',
        ]);

    $identity = googleProvider($httpClient, $verifier)->fetchIdentity(
        authorizationCode: 'auth-code',
        redirectUri: 'http://localhost:3000/auth/google/callback',
        codeVerifier: 'verifier',
    );

    expect($identity->provider)->toBe(IdentityProvider::GOOGLE)
        ->and($identity->subject->value())->toBe('google-sub')
        ->and($identity->email->value())->toBe('user@example.com')
        ->and($identity->emailVerified)->toBeTrue()
        ->and($identity->name)->toBe('Google User')
        ->and($identity->pictureUrl)->toBe('https://example.com/photo.jpg');
});

it('rejects a redirect uri outside the allowlist', function () {
    $httpClient = new MockHttpClient();
    $verifier = Mockery::mock(IdTokenVerifierInterface::class);

    googleProvider($httpClient, $verifier)->fetchIdentity(
        authorizationCode: 'auth-code',
        redirectUri: 'https://evil.example.com/callback',
    );
})->throws(SocialAuthenticationException::class);

it('rejects a token endpoint error', function () {
    $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(
        json_encode(['error' => 'invalid_grant'], JSON_THROW_ON_ERROR),
        ['http_code' => 400],
    ));
    $verifier = Mockery::mock(IdTokenVerifierInterface::class);

    googleProvider($httpClient, $verifier)->fetchIdentity(
        authorizationCode: 'bad-code',
        redirectUri: 'http://localhost:3000/auth/google/callback',
    );
})->throws(SocialAuthenticationException::class);

it('rejects a provider response without id_token', function () {
    $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(
        json_encode([], JSON_THROW_ON_ERROR),
        ['http_code' => 200],
    ));
    $verifier = Mockery::mock(IdTokenVerifierInterface::class);

    googleProvider($httpClient, $verifier)->fetchIdentity(
        authorizationCode: 'auth-code',
        redirectUri: 'http://localhost:3000/auth/google/callback',
    );
})->throws(SocialAuthenticationException::class);
