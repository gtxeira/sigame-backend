<?php

declare(strict_types=1);

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Presentation\Http\Response\CurrentUserResponse;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

function authTestCreateUser(
    FunctionalTestCase $test,
    string $email = 'auth@example.com',
    string $password = 'secret123',
    ?string $cpf = null,
): User {
    $container = $test->api->getKernelBrowser()->getContainer();

    $user = User::register(
        cpf: null === $cpf ? null : Cpf::fromString($cpf)->value(),
        email: Email::fromString($email)->value(),
        /* @phpstan-ignore-next-line */
        password: $container->get(PasswordHasherInterface::class)->hash($password),
        name: 'Auth Test User',
    );

    /* @phpstan-ignore-next-line */
    $container->get(UserRepositoryInterface::class)->save($user);

    return $user;
}

function authTestLogin(
    FunctionalTestCase $test,
    string $email = 'auth@example.com',
    string $password = 'secret123',
): Response {
    return $test->api->post('app_auth_login', [
        'email' => $email,
        'password' => $password,
    ]);
}

/**
 * @return array<string, mixed>
 */
function authTestBody(Response $response): array
{
    /** @var array<string, mixed> $body */
    $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $body;
}

function authTestAccessToken(Response $response): string
{
    return (string) authTestBody($response)['data']['accessToken'];
}

function authTestRefreshCookie(Response $response): ?string
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ('refresh_token' === $cookie->getName()) {
            return $cookie->getValue();
        }
    }

    return null;
}

function authTestSetRefreshCookie(FunctionalTestCase $test, string $value): void
{
    $jar = $test->api->getKernelBrowser()->getCookieJar();
    $jar->clear();
    $jar->set(new Cookie('refresh_token', $value, null, '/api/v1/auth'));
}

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

it('rejects an access token from a suspended account', function () {
    $user = authTestCreateUser($this, 'suspended-token@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'suspended-token@example.com'));

    $user->suspend('violation');
    // @phpstan-ignore-next-line
    $this->api->getKernelBrowser()->getContainer()->get(UserRepositoryInterface::class)->save($user);

    $response = $this->api->get('app_auth_me', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
    ]);

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

it('returns the authenticated user on me', function () {
    authTestCreateUser($this, 'me@example.com');
    $login = authTestLogin($this, 'me@example.com');
    $accessToken = authTestAccessToken($login);

    $response = $this->api->get('app_auth_me', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $me = $this->api->deserializeResponse(CurrentUserResponse::class);

    expect($me->email)->toBe('me@example.com')
        ->and($me->cpf)->toBeNull();
});

it('rejects me without an access token', function () {
    $response = $this->api->get('app_auth_me');

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['UNAUTHORIZED']);
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

it('defines the cpf once and rejects changes', function () {
    authTestCreateUser($this, 'cpf@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'cpf@example.com'));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $first = $this->api->patch('app_auth_me_cpf', ['cpf' => '52998224725'], server: $server);

    expect($first->getStatusCode())->toBe(Response::HTTP_OK);

    $body = authTestBody($first);

    expect($body['data']['cpf'])->toBe('529.982.247-25');

    $same = $this->api->patch('app_auth_me_cpf', ['cpf' => '529.982.247-25'], server: $server);

    expect($same->getStatusCode())->toBe(Response::HTTP_OK);

    $different = $this->api->patch('app_auth_me_cpf', ['cpf' => '47705122014'], server: $server);

    expect($different->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['USER_CPF_ALREADY_SET']);
});

it('rejects a cpf that belongs to another user', function () {
    authTestCreateUser($this, 'cpf-owner@example.com', cpf: '52998224725');
    authTestCreateUser($this, 'cpf-other@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'cpf-other@example.com'));

    $response = $this->api->patch(
        'app_auth_me_cpf',
        ['cpf' => '529.982.247-25'],
        server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['USER_CPF_ALREADY_EXISTS']);
});

it('rejects an invalid cpf', function () {
    authTestCreateUser($this, 'cpf-invalid@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'cpf-invalid@example.com'));

    $response = $this->api->patch(
        'app_auth_me_cpf',
        ['cpf' => '111.111.111-11'],
        server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['CPF_INVALID']);
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
