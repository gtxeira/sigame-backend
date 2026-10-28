<?php

declare(strict_types=1);

use App\Identity\Domain\ValueObject\UserLevel;
use App\Identity\Domain\ValueObject\UserStatus;
use App\Identity\Presentation\Http\Controller\RegistrationController;
use App\Identity\Presentation\Http\Response\RegisterUserResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Helpers\Factories\RegisterUserRequestFactory;

it('returns 201 created with valid data', function () {
    $cpf = faker()->cpf();
    $name = faker()->name();

    $payload = RegisterUserRequestFactory::new([
        'cpf' => $cpf,
        'name' => $name,
    ])->create();

    $response = $this->controllersTestsHelper->post(
        controllerAction: [RegistrationController::class, 'register'],
        payload: $payload,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    $user = $this->controllersTestsHelper->deserializeResponse(RegisterUserResponse::class);

    expect($user->cpf)->toBe($cpf)
        ->and($user->name)->toBe($name)
        ->and($user->level)->toBe(UserLevel::MIN_LEVEL)
        ->and($user->status)->toBe(UserStatus::ACTIVE->value);
});

it('rejects duplicate email', function () {
    $email = faker()->email();

    $jsonRequest = fn () => $this->controllersTestsHelper->post(
        controllerAction: [RegistrationController::class, 'register'],
        payload: RegisterUserRequestFactory::new(['email' => $email])->create(),
    );

    $response = $jsonRequest();
    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    $response = $jsonRequest();
    expect($response->getStatusCode())->not->toBe(Response::HTTP_CREATED);
});

it('rejects invalid registration payload', function (array $overrides) {
    $response = $this->controllersTestsHelper->post(
        controllerAction: [RegistrationController::class, 'register'],
        payload: RegisterUserRequestFactory::new($overrides)->create(),
    );

    expect($response->getStatusCode())->not->toBe(Response::HTTP_CREATED);
})->with([
    'invalid cpf' => [fn () => ['cpf' => faker()->numerify()]],
    'short password' => [fn () => ['password' => faker()->numerify()]],
]);
