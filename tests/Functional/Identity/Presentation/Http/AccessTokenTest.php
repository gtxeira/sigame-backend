<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

it('ignores an invalid bearer token on login with valid credentials', function () {
    authTestCreateUser($this, 'public-login@example.com');

    $response = $this->api->post(
        'app_auth_login',
        ['email' => 'public-login@example.com', 'password' => 'secret123'],
        server: ['HTTP_AUTHORIZATION' => 'Bearer invalid-token'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestAccessToken($response))->not->toBeEmpty();
});

it('keeps invalid credentials when login receives an invalid bearer token', function () {
    authTestCreateUser($this, 'public-login-invalid@example.com');

    $response = $this->api->post(
        'app_auth_login',
        ['email' => 'public-login-invalid@example.com', 'password' => 'wrong-password'],
        server: ['HTTP_AUTHORIZATION' => 'Bearer invalid-token'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['INVALID_CREDENTIALS']);
});

it('rotates the refresh token even when the request carries an invalid bearer token', function () {
    authTestCreateUser($this, 'public-refresh@example.com');
    $login = authTestLogin($this, 'public-refresh@example.com');
    $oldRefresh = (string) authTestRefreshCookie($login);

    $response = $this->api->post(
        'app_auth_refresh',
        server: ['HTTP_AUTHORIZATION' => 'Bearer invalid-token'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestRefreshCookie($response))->toBeString()->not->toBe($oldRefresh);
});

it('reports an invalid refresh token without a cookie even with an invalid bearer token', function () {
    $response = $this->api->post(
        'app_auth_refresh',
        server: ['HTTP_AUTHORIZATION' => 'Bearer invalid-token'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['REFRESH_TOKEN_INVALID']);
});

it('logs out even when the request carries an invalid bearer token', function () {
    authTestCreateUser($this, 'public-logout@example.com');
    authTestLogin($this, 'public-logout@example.com');

    $response = $this->api->post(
        'app_auth_logout',
        server: ['HTTP_AUTHORIZATION' => 'Bearer invalid-token'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);
});

it('starts an anonymous social flow even with an invalid bearer token', function () {
    $response = $this->api->post(
        'app_auth_social_start',
        ['client' => 'web'],
        ['provider' => 'google'],
        ['HTTP_AUTHORIZATION' => 'Bearer invalid-token'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

it('rejects a malformed access token on a protected route', function () {
    $response = $this->api->get('app_me', server: [
        'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['ACCESS_TOKEN_INVALID'])
        ->and($response->headers->get('WWW-Authenticate'))->toContain('error="invalid_token"');
});

it('rejects an expired access token on a protected route', function () {
    $user = authTestCreateUser($this, 'expired-token@example.com');
    $token = authTestExpiredAccessToken($user->id());

    $response = $this->api->get('app_me', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['ACCESS_TOKEN_EXPIRED'])
        ->and($response->headers->get('WWW-Authenticate'))->toContain('error="invalid_token"');
});

it('reports a missing access token when the bearer value is empty', function () {
    $response = $this->api->get('app_me', server: [
        'HTTP_AUTHORIZATION' => 'Bearer ',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['ACCESS_TOKEN_MISSING'])
        ->and($response->headers->get('WWW-Authenticate'))->toBe('Bearer realm="sigame-api"');
});

it('challenges a missing access token without the invalid_token error', function () {
    $response = $this->api->get('app_me');

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['ACCESS_TOKEN_MISSING'])
        ->and($response->headers->get('WWW-Authenticate'))->toBe('Bearer realm="sigame-api"');
});
