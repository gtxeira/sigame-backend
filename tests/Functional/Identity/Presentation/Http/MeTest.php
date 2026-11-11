<?php

declare(strict_types=1);

use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Presentation\Http\Response\CurrentUserResponse;
use Symfony\Component\HttpFoundation\Response;

it('returns the authenticated user on me', function () {
    authTestCreateUser($this, 'me@example.com');
    $login = authTestLogin($this, 'me@example.com');
    $accessToken = authTestAccessToken($login);

    $response = $this->api->get('app_me', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $me = $this->api->deserializeResponse(CurrentUserResponse::class);

    expect($me->email)->toBe('me@example.com')
        ->and($me->cpf)->toBeNull()
        ->and($me->emailVerified)->toBeFalse();
});

it('reports the verified email state on me', function () {
    $email = 'me-verified@example.com';

    $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Verified Me',
        'password' => 'secret123',
    ]);

    $token = (string) authTestMailer($this)->lastToken('verification', $email);
    $this->api->post('app_auth_email_verify', ['token' => $token]);

    $accessToken = authTestAccessToken(authTestLogin($this, $email));
    $response = $this->api->get('app_me', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $me = $this->api->deserializeResponse(CurrentUserResponse::class);

    expect($me->emailVerified)->toBeTrue();
});

it('rejects me without an access token', function () {
    $response = $this->api->get('app_me');

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['ACCESS_TOKEN_MISSING']);
});

it('rejects an access token from a suspended account', function () {
    $user = authTestCreateUser($this, 'suspended-token@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'suspended-token@example.com'));

    $user->suspend();
    // @phpstan-ignore-next-line
    $this->api->getKernelBrowser()->getContainer()->get(UserRepositoryInterface::class)->save($user);

    $response = $this->api->get('app_me', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['ACCOUNT_SUSPENDED']);
});

it('defines the cpf once and rejects changes', function () {
    authTestCreateUser($this, 'cpf@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'cpf@example.com'));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $first = $this->api->patch('app_me_cpf', ['cpf' => '52998224725'], server: $server);

    expect($first->getStatusCode())->toBe(Response::HTTP_OK);

    $body = authTestBody($first);

    expect($body['data']['cpf'])->toBe('529.982.247-25');

    $same = $this->api->patch('app_me_cpf', ['cpf' => '529.982.247-25'], server: $server);

    expect($same->getStatusCode())->toBe(Response::HTTP_OK);

    $different = $this->api->patch('app_me_cpf', ['cpf' => '47705122014'], server: $server);

    expect($different->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['USER_CPF_ALREADY_SET']);
});

it('rejects a cpf that belongs to another user', function () {
    authTestCreateUser($this, 'cpf-owner@example.com', cpf: '52998224725');
    authTestCreateUser($this, 'cpf-other@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'cpf-other@example.com'));

    $response = $this->api->patch(
        'app_me_cpf',
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
        'app_me_cpf',
        ['cpf' => '111.111.111-11'],
        server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['INVALID_FORMAT']);
});
