<?php

declare(strict_types=1);

use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

it('logs in with valid credentials and sets an opaque refresh cookie', function () {
    authTestCreateUser($this);

    $response = authTestLogin($this);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $body = authTestBody($response);

    expect($body['data']['tokenType'])->toBe('Bearer')
        ->and($body['data']['expiresIn'])->toBeInt()
        ->and($body['data']['accessToken'])->toBeString()->not->toBeEmpty()
        ->and(authTestRefreshCookie($response))->toBeString()->toStartWith('rt_');
});

it('returns the same invalid credentials error for unknown email and wrong password', function (string $email, string $password) {
    authTestCreateUser($this);

    $response = authTestLogin($this, $email, $password);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['INVALID_CREDENTIALS']);
})->with([
    'unknown email' => ['unknown@example.com', 'secret123'],
    'wrong password' => ['auth@example.com', 'wrong-password'],
]);

it('rejects login from a suspended account', function () {
    $user = authTestCreateUser($this);
    $user->suspend();
    /* @phpstan-ignore-next-line */
    $this->api->getKernelBrowser()->getContainer()->get(UserRepositoryInterface::class)->save($user);

    $response = authTestLogin($this);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['ACCOUNT_SUSPENDED']);
});

it('rejects refresh from a suspended account', function () {
    $user = authTestCreateUser($this, 'suspended-refresh@example.com');
    authTestLogin($this, 'suspended-refresh@example.com');

    $user->suspend();
    // @phpstan-ignore-next-line
    $this->api->getKernelBrowser()->getContainer()->get(UserRepositoryInterface::class)->save($user);

    $response = $this->api->post('app_auth_refresh');

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['ACCOUNT_SUSPENDED']);
});

it('rotates both tokens on refresh and rejects the old refresh token', function () {
    authTestCreateUser($this, 'refresh@example.com');
    $login = authTestLogin($this, 'refresh@example.com');
    $oldRefresh = (string) authTestRefreshCookie($login);
    $oldAccess = authTestAccessToken($login);

    $refresh = $this->api->post('app_auth_refresh');

    expect($refresh->getStatusCode())->toBe(Response::HTTP_OK);

    $newRefresh = (string) authTestRefreshCookie($refresh);
    $newAccess = authTestAccessToken($refresh);

    expect($newRefresh)->not->toBe($oldRefresh)
        ->and($newAccess)->not->toBe($oldAccess);

    authTestSetRefreshCookie($this, $oldRefresh);

    $reuse = $this->api->post('app_auth_refresh');

    expect($reuse->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['REFRESH_TOKEN_REUSED']);

    authTestSetRefreshCookie($this, $newRefresh);

    $afterFamilyRevocation = $this->api->post('app_auth_refresh');

    expect($afterFamilyRevocation->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});

it('invalidates the refresh token on logout', function () {
    authTestCreateUser($this, 'logout@example.com');
    $login = authTestLogin($this, 'logout@example.com');
    $refresh = (string) authTestRefreshCookie($login);

    $logout = $this->api->post('app_auth_logout');

    expect($logout->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

    authTestSetRefreshCookie($this, $refresh);

    $afterLogout = $this->api->post('app_auth_refresh');

    expect($afterLogout->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});

it('rejects an unsupported social provider', function () {
    $response = $this->api->post(
        'app_auth_social_start',
        ['client' => 'web'],
        ['provider' => 'apple'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_PROVIDER_UNSUPPORTED']);
});

it('validates the social start payload', function () {
    $response = $this->api->post(
        'app_auth_social_start',
        [],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);
});

it('starts a social flow with state, PKCE and a flow cookie', function () {
    $response = $this->api->post(
        'app_auth_social_start',
        ['client' => 'web'],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $body = authTestBody($response);

    expect($body['data']['authorizationUrl'])
        ->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->toContain('code_challenge_method=S256')
        ->and($body['data']['state'])->toBeString()->not->toBeEmpty()
        ->and($body['data']['expiresIn'])->toBe(600)
        ->and(authTestSocialFlowCookie($response))->toBeString()->not->toBeEmpty();
});

it('rejects a social callback without the flow cookie', function () {
    $start = $this->api->post(
        'app_auth_social_start',
        ['client' => 'web'],
        ['provider' => 'google'],
    );
    $state = authTestBody($start)['data']['state'];

    $this->api->getKernelBrowser()->getCookieJar()->clear();

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'user@example.com', 'state' => $state],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_FLOW_INVALID']);
});

it('rate limits repeated login attempts', function () {
    authTestCreateUser($this, 'rate-limit@example.com');

    for ($attempt = 0; $attempt < 5; ++$attempt) {
        authTestLogin($this, 'rate-limit@example.com', 'wrong-password');
    }

    $response = authTestLogin($this, 'rate-limit@example.com', 'wrong-password');

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS)
        ->and($this->api->errorCodes())->toBe(['TOO_MANY_REQUESTS']);
});

it('rejects a cookie refresh from a disallowed origin', function () {
    $response = $this->api->post('app_auth_refresh', server: [
        'HTTP_ORIGIN' => 'https://evil.example.com',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['ORIGIN_NOT_ALLOWED']);
});

it('accepts a cookie refresh from an allowed origin', function () {
    authTestCreateUser($this, 'origin@example.com');
    authTestLogin($this, 'origin@example.com');

    $response = $this->api->post('app_auth_refresh', server: [
        'HTTP_ORIGIN' => 'http://localhost:3000',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestRefreshCookie($response))->toBeString()->toStartWith('rt_');
});
