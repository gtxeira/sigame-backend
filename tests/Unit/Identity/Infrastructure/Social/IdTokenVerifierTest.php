<?php

declare(strict_types=1);

use App\Identity\Infrastructure\Social\IdTokenVerifier;
use App\Identity\Infrastructure\Social\JwksProvider;
use Firebase\JWT\JWT;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @return array{private: string, jwks: array{keys: list<array<string, mixed>>}}
 */
function idTokenTestKey(string $kid = 'kid-1'): array
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    assert(false !== $resource);

    openssl_pkey_export($resource, $privateKey);
    $details = openssl_pkey_get_details($resource);
    assert(false !== $details);

    $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

    return [
        'private' => (string) $privateKey,
        'jwks' => [
            'keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => $kid,
                'n' => $encode($details['rsa']['n']),
                'e' => $encode($details['rsa']['e']),
            ]],
        ],
    ];
}

/**
 * @param array<string, mixed> $claims
 */
function idTokenTestEncode(string $privateKey, array $claims, string $kid = 'kid-1'): string
{
    return JWT::encode($claims, $privateKey, 'RS256', $kid);
}

/**
 * @param array{keys: list<array<string, mixed>>} $jwks
 */
function idTokenTestVerifier(array $jwks, ?callable $httpHandler = null): IdTokenVerifier
{
    $httpClient = new MockHttpClient(
        $httpHandler ?? static fn (): MockResponse => new MockResponse(
            json_encode($jwks, JSON_THROW_ON_ERROR),
            ['http_code' => 200],
        ),
    );

    return new IdTokenVerifier(new JwksProvider($httpClient, new ArrayAdapter(), 3600));
}

/**
 * @return array<string, mixed>
 */
function idTokenTestClaims(string $audience = 'client-id', string $issuer = 'https://accounts.google.com'): array
{
    return [
        'iss' => $issuer,
        'aud' => $audience,
        'sub' => 'google-sub',
        'email' => 'user@example.com',
        'email_verified' => true,
        'iat' => time(),
        'exp' => time() + 300,
    ];
}

it('accepts a valid google id_token', function () {
    $key = idTokenTestKey();
    $token = idTokenTestEncode($key['private'], idTokenTestClaims());

    $claims = idTokenTestVerifier($key['jwks'])->verify(
        $token,
        'https://www.googleapis.com/oauth2/v3/certs',
        'client-id',
        ['https://accounts.google.com'],
    );

    expect($claims['sub'])->toBe('google-sub')
        ->and($claims['email'])->toBe('user@example.com');
});

it('rejects a token from another issuer', function () {
    $key = idTokenTestKey();
    $token = idTokenTestEncode($key['private'], idTokenTestClaims(issuer: 'https://evil.example.com'));

    idTokenTestVerifier($key['jwks'])->verify(
        $token,
        'https://www.googleapis.com/oauth2/v3/certs',
        'client-id',
        ['https://accounts.google.com'],
    );
})->throws(UnexpectedValueException::class);

it('rejects a token for another audience', function () {
    $key = idTokenTestKey();
    $token = idTokenTestEncode($key['private'], idTokenTestClaims(audience: 'other-client'));

    idTokenTestVerifier($key['jwks'])->verify(
        $token,
        'https://www.googleapis.com/oauth2/v3/certs',
        'client-id',
        ['https://accounts.google.com'],
    );
})->throws(UnexpectedValueException::class);

it('rejects a token without expiration', function () {
    $key = idTokenTestKey();
    $claims = idTokenTestClaims();
    unset($claims['exp']);
    $token = idTokenTestEncode($key['private'], $claims);

    idTokenTestVerifier($key['jwks'])->verify(
        $token,
        'https://www.googleapis.com/oauth2/v3/certs',
        'client-id',
        ['https://accounts.google.com'],
    );
})->throws(UnexpectedValueException::class);

it('rejects an expired token', function () {
    $key = idTokenTestKey();
    $claims = idTokenTestClaims();
    $claims['exp'] = time() - 300;
    $token = idTokenTestEncode($key['private'], $claims);

    idTokenTestVerifier($key['jwks'])->verify(
        $token,
        'https://www.googleapis.com/oauth2/v3/certs',
        'client-id',
        ['https://accounts.google.com'],
    );
})->throws(UnexpectedValueException::class);

it('requires the authorized party when the audience is a list', function () {
    $key = idTokenTestKey();
    $claims = idTokenTestClaims();
    $claims['aud'] = ['client-id', 'other-client'];
    $claims['azp'] = 'other-client';
    $token = idTokenTestEncode($key['private'], $claims);

    idTokenTestVerifier($key['jwks'])->verify(
        $token,
        'https://www.googleapis.com/oauth2/v3/certs',
        'client-id',
        ['https://accounts.google.com'],
    );
})->throws(UnexpectedValueException::class);

it('refreshes the jwks when the token uses a new key', function () {
    $oldKey = idTokenTestKey('kid-old');
    $newKey = idTokenTestKey('kid-new');
    $token = idTokenTestEncode($newKey['private'], idTokenTestClaims(), 'kid-new');
    $requests = 0;

    $verifier = idTokenTestVerifier($oldKey['jwks'], function () use (&$requests, $oldKey, $newKey): MockResponse {
        ++$requests;

        return new MockResponse(
            json_encode(1 === $requests ? $oldKey['jwks'] : $newKey['jwks'], JSON_THROW_ON_ERROR),
            ['http_code' => 200],
        );
    });

    $claims = $verifier->verify(
        $token,
        'https://www.googleapis.com/oauth2/v3/certs',
        'client-id',
        ['https://accounts.google.com'],
    );

    expect($claims['sub'])->toBe('google-sub')
        ->and($requests)->toBe(2);
});
