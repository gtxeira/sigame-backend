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
    $user->suspend('violation');
    /* @phpstan-ignore-next-line */
    $this->api->getKernelBrowser()->getContainer()->get(UserRepositoryInterface::class)->save($user);

    $response = authTestLogin($this);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['ACCOUNT_SUSPENDED']);
});

it('rejects refresh from a suspended account', function () {
    $user = authTestCreateUser($this, 'suspended-refresh@example.com');
    authTestLogin($this, 'suspended-refresh@example.com');

    $user->suspend('violation');
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
        'app_auth_social',
        [
            'code' => 'code',
            'redirectUri' => 'http://localhost:3000/auth/google/callback',
        ],
        ['provider' => 'apple'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_PROVIDER_UNSUPPORTED']);
});

it('validates the social login payload', function () {
    $response = $this->api->post(
        'app_auth_social',
        ['redirectUri' => 'http://localhost:3000/auth/google/callback'],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);
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
