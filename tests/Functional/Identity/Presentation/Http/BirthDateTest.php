<?php

declare(strict_types=1);

use App\Identity\Presentation\Http\Response\RegisterUserResponse;
use Symfony\Component\HttpFoundation\Response;

it('defines the birth date once and rejects changes', function () {
    authTestCreateUser($this, 'birth-once@example.com', birthDate: null);
    $accessToken = authTestAccessToken(authTestLogin($this, 'birth-once@example.com'));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $first = $this->api->patch('app_me_birth_date', ['birthDate' => '1990-05-12'], server: $server);

    expect($first->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($first)['data'];

    expect($data['birthDate'])->toBe('1990-05-12')
        ->and($data['age'])->toBeInt()
        ->and($data['age'])->toBeGreaterThanOrEqual(18);

    $same = $this->api->patch('app_me_birth_date', ['birthDate' => '1990-05-12'], server: $server);

    expect($same->getStatusCode())->toBe(Response::HTTP_OK);

    $different = $this->api->patch('app_me_birth_date', ['birthDate' => '1991-01-01'], server: $server);

    expect($different->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['USER_BIRTH_DATE_ALREADY_SET']);
});

it('rejects a missing birth date', function () {
    authTestCreateUser($this, 'birth-missing@example.com', birthDate: null);
    $accessToken = authTestAccessToken(authTestLogin($this, 'birth-missing@example.com'));

    $response = $this->api->patch(
        'app_me_birth_date',
        [],
        server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['FIELD_REQUIRED']);
});

it('rejects an impossible birth date', function () {
    authTestCreateUser($this, 'birth-invalid@example.com', birthDate: null);
    $accessToken = authTestAccessToken(authTestLogin($this, 'birth-invalid@example.com'));

    $response = $this->api->patch(
        'app_me_birth_date',
        ['birthDate' => '1990-02-30'],
        server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['INVALID_FORMAT']);
});

it('rejects an underage account and removes it', function () {
    $email = 'birth-underage@example.com';

    $this->api->post('app_auth_register', [
        'cpf' => null,
        'email' => $email,
        'name' => 'Underage User',
        'password' => 'secret1234',
    ]);

    $registered = $this->api->deserializeResponse(RegisterUserResponse::class);
    $accessToken = authTestAccessToken(authTestLogin($this, $email, 'secret1234'));

    $response = $this->api->patch(
        'app_me_birth_date',
        ['birthDate' => '2010-01-01'],
        server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['USER_UNDER_MINIMUM_AGE']);

    $login = authTestLogin($this, $email, 'secret1234');

    expect($login->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['INVALID_CREDENTIALS']);

    $progressCount = authTestEntityManager($this)->getConnection()->fetchOne(
        'SELECT COUNT(*) FROM user_progress WHERE user_id = ?',
        [$registered->id],
    );

    expect((int) $progressCount)->toBe(0);
});

it('requires the birth date to complete onboarding', function () {
    authTestCreateUser($this, 'birth-onboarding@example.com', birthDate: null);
    $accessToken = authTestAccessToken(authTestLogin($this, 'birth-onboarding@example.com'));

    $response = $this->api->post(
        'app_me_onboarding',
        ['modalities' => ['corrida']],
        server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['USER_BIRTH_DATE_REQUIRED']);
});

it('rejects an unauthenticated birth date update', function () {
    $response = $this->api->patch('app_me_birth_date', ['birthDate' => '1990-05-12']);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});
