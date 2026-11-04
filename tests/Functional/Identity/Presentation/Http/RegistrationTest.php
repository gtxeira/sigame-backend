<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\UserLevel;
use App\Identity\Domain\User\ValueObject\UserStatus;
use App\Identity\Presentation\Http\Response\RegisterUserResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\Identity\Presentation\Http\Request\RegisterUserRequestFactory;

it('returns 201 created with valid data', function () {
    $cpf = faker()->cpf();
    $name = faker()->name();

    $payload = RegisterUserRequestFactory::new([
        'cpf' => $cpf,
        'name' => $name,
    ])->create();

    $response = $this->api->post(
        route: 'app_auth_register',
        payload: $payload,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    $user = $this->api->deserializeResponse(RegisterUserResponse::class);

    expect($user->cpf)->toBe($cpf)
        ->and($user->name)->toBe($name)
        ->and($user->level)->toBe(UserLevel::MIN_LEVEL)
        ->and($user->status)->toBe(UserStatus::ACTIVE->value);
});

it('registers without cpf', function () {
    $email = faker()->unique()->safeEmail();

    $response = $this->api->post(
        route: 'app_auth_register',
        payload: [
            'cpf' => null,
            'email' => $email,
            'name' => faker()->name(),
            'password' => 'secret1234',
        ],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    $user = $this->api->deserializeResponse(RegisterUserResponse::class);

    expect($user->cpf)->toBeNull()
        ->and($user->email)->toBe($email);
});

it('rejects duplicate email', function () {
    $email = faker()->email();

    $jsonRequest = fn () => $this->api->post(
        route: 'app_auth_register',
        payload: RegisterUserRequestFactory::new(['email' => $email])->create(),
    );

    $response = $jsonRequest();
    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    $response = $jsonRequest();
    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['EMAIL_ALREADY_EXISTS']);
});

it('rejects duplicate cpf', function () {
    $cpf = faker()->cpf();

    $jsonRequest = fn () => $this->api->post(
        route: 'app_auth_register',
        payload: RegisterUserRequestFactory::new(['cpf' => $cpf])->create(),
    );

    $response = $jsonRequest();
    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    $response = $jsonRequest();
    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['USER_CPF_ALREADY_EXISTS']);
});

it('rejects cpf that fails domain validation', function (string $cpf) {
    $response = $this->api->post(
        route: 'app_auth_register',
        payload: RegisterUserRequestFactory::new(['cpf' => $cpf])->create(),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['CPF_INVALID']);
})->with([
    'repeated digits' => '11111111111',
    'invalid check digits' => '52998224726',
]);

it('rejects invalid registration payload', function (array $overrides) {
    $response = $this->api->post(
        route: 'app_auth_register',
        payload: RegisterUserRequestFactory::new($overrides)->create(),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);
})->with([
    'invalid cpf' => [fn () => ['cpf' => faker()->numerify()]],
    'short password' => [fn () => ['password' => faker()->numerify()]],
]);

it('translates Symfony Validator messages to pt-BR', function () {
    $response = $this->api->post(
        route: 'app_auth_register',
        payload: [
            'cpf' => faker()->cpf(),
            'email' => faker()->email(),
            'name' => ['invalid type'],
            'password' => 'secret1234',
        ],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);

    /** @var array{errors: list<array{code: string, message: string, details: list<array{field: string, message: string}>}>} $body */
    $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($body['errors'][0]['code'])->toBe('VALIDATION_ERROR')
        ->and($body['errors'][0]['details'][0]['field'])->toBe('name')
        ->and($body['errors'][0]['details'][0]['message'])->toBe('Este valor deve ser do tipo string.');
});
