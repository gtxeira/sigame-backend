<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * @return array{state: string, cookie: \Symfony\Component\HttpFoundation\Cookie|null}
 */
function hardeningStart(FunctionalTestCase $test, ?string $accessToken = null): array
{
    $server = null === $accessToken ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $response = $test->api->post(
        'app_auth_social_start',
        ['client' => 'web'],
        ['provider' => 'google'],
        $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    authTestSetSocialFlowCookie($test, (string) authTestSocialFlowCookie($response));

    return [
        'state' => (string) authTestBody($response)['data']['state'],
        'cookie' => authTestSocialFlowCookieObject($response),
    ];
}

it('requires a bearer token on every protected account route', function (
    string $route,
    array $routeParams,
) {
    $response = $this->api->post($route, [], $routeParams);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['ACCESS_TOKEN_MISSING']);
})->with([
    'email resend' => ['app_auth_email_resend', []],
    'password change' => ['app_me_password_change', []],
    'identity link' => ['app_me_identity_link', ['provider' => 'google']],
    'onboarding' => ['app_me_onboarding', []],
    'photo upload' => ['app_me_photo_update', []],
]);

it('requires a bearer token on partial profile and photo removal routes', function (
    string $method,
    string $route,
) {
    $response = match ($method) {
        'PATCH' => $this->api->patch($route),
        'DELETE' => $this->api->delete($route),
        default => throw new LogicException(sprintf('Método não suportado: %s.', $method)),
    };

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['ACCESS_TOKEN_MISSING']);
})->with([
    'profile update' => ['PATCH', 'app_me_profile_update'],
    'photo removal' => ['DELETE', 'app_me_photo_remove'],
]);

it('authenticates an existing social identity without creating a duplicate account', function () {
    $email = 'existing-social@example.com';
    authTestCreateSocialUser($this, $email);

    $flow = hardeningStart($this);

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => $email, 'state' => $flow['state']],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestRefreshCookie($response))->toBeString()->toStartWith('rt_');

    $user = authTestUserRepository($this)->findByEmail(Email::fromString($email)->value());

    expect($user?->identities())->toHaveCount(1);
});

it('rejects a social login for a suspended account', function () {
    $email = 'suspended-social@example.com';
    authTestCreateSocialUser($this, $email, suspended: true);

    $flow = hardeningStart($this);

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => $email, 'state' => $flow['state']],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['ACCOUNT_SUSPENDED']);
});

it('rejects a password reset for an account suspended after the request', function () {
    $email = 'suspended-after-request@example.com';
    $user = authTestCreateUser($this, $email);

    $this->api->post('app_auth_password_forgot', ['email' => $email]);
    $token = (string) authTestMailer($this)->lastToken('password_reset', $email);

    $user->suspend();
    authTestUserRepository($this)->save($user);

    $response = $this->api->post('app_auth_password_reset', [
        'token' => $token,
        'password' => 'new-secret-123',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['ACCOUNT_SUSPENDED']);
});

it('sets a hardened social flow cookie', function () {
    $flow = hardeningStart($this);

    expect($flow['cookie'])->not->toBeNull()
        ->and($flow['cookie']?->isHttpOnly())->toBeTrue()
        ->and($flow['cookie']?->getPath())->toBe('/api/v1')
        ->and($flow['cookie']?->getSameSite())->toBe('strict')
        ->and($flow['cookie']?->isSecure())->toBeFalse();
});

it('rejects a logout from a disallowed origin', function () {
    $response = $this->api->post('app_auth_logout', server: [
        'HTTP_ORIGIN' => 'https://evil.example.com',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['ORIGIN_NOT_ALLOWED']);
});

it('rate limits repeated social flow starts', function () {
    for ($attempt = 0; $attempt < 20; ++$attempt) {
        $this->api->post(
            'app_auth_social_start',
            ['client' => 'web'],
            ['provider' => 'google'],
        );
    }

    $response = $this->api->post(
        'app_auth_social_start',
        ['client' => 'web'],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rate limits repeated social callbacks', function () {
    for ($attempt = 0; $attempt < 10; ++$attempt) {
        $this->api->post(
            'app_auth_social_callback',
            ['code' => 'user@example.com', 'state' => 'state'],
            ['provider' => 'google'],
        );
    }

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'user@example.com', 'state' => 'state'],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rate limits repeated social link attempts', function () {
    $email = 'link-limit@example.com';
    authTestCreateUser($this, $email);
    $accessToken = authTestAccessToken(authTestLogin($this, $email));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    for ($attempt = 0; $attempt < 10; ++$attempt) {
        $this->api->post(
            'app_auth_social_link',
            ['linkToken' => 'invalid-'.$attempt],
            ['provider' => 'google'],
            $server,
        );
    }

    $response = $this->api->post(
        'app_auth_social_link',
        ['linkToken' => 'invalid-10'],
        ['provider' => 'google'],
        $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rejects a social callback for a provider other than google', function () {
    $flow = hardeningStart($this);

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'user@example.com', 'state' => $flow['state']],
        ['provider' => 'apple'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_PROVIDER_UNSUPPORTED']);
});

it('rejects a social link for a provider other than google', function () {
    $email = 'provider-link@example.com';
    authTestCreateUser($this, $email);
    $accessToken = authTestAccessToken(authTestLogin($this, $email));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $response = $this->api->post(
        'app_auth_social_link',
        ['linkToken' => 'any-token'],
        ['provider' => 'apple'],
        $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_PROVIDER_UNSUPPORTED']);
});
