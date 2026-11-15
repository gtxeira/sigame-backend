<?php

declare(strict_types=1);

use App\Identity\Presentation\Http\Response\RegistrationAcceptedResponse;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\Identity\Presentation\Http\Request\RegisterUserRequestFactory;

it('accepts a registration and sends a verification code', function () {
    $email = faker()->unique()->safeEmail();
    $name = faker()->name();

    $response = $this->api->post(
        route: 'app_auth_register',
        payload: RegisterUserRequestFactory::new([
            'email' => $email,
            'name' => $name,
        ])->create(),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_ACCEPTED);

    $accepted = $this->api->deserializeResponse(RegistrationAcceptedResponse::class);

    expect($accepted->status)->toBe('pending');

    $user = authTestUserRepository($this)->findByEmail(Email::fromString($email)->value());

    expect($user)->not->toBeNull()
        ->and($user?->name())->toBe($name)
        ->and($user?->cpf())->toBeNull()
        ->and($user?->isEmailVerified())->toBeFalse()
        ->and(authTestMailer($this)->lastCode('verification', $email))->toBeString();
});

it('returns the same accepted response for an existing email and notifies the owner', function () {
    $email = 'existing-register@example.com';
    authTestCreateUser($this, $email);

    $payload = RegisterUserRequestFactory::new(['email' => $email])->create();

    $response = $this->api->post('app_auth_register', $payload);

    expect($response->getStatusCode())->toBe(Response::HTTP_ACCEPTED);

    $accepted = $this->api->deserializeResponse(RegistrationAcceptedResponse::class);

    expect($accepted->status)->toBe('pending')
        ->and(authTestMailer($this)->count('account_exists', $email))->toBe(1)
        ->and(authTestMailer($this)->count('verification', $email))->toBe(0);
});

it('does not create a second account for an existing email', function () {
    $email = 'no-second-account@example.com';
    $existing = authTestCreateUser($this, $email);

    $this->api->post(
        'app_auth_register',
        RegisterUserRequestFactory::new(['email' => $email])->create(),
    );

    $connection = authTestEntityManager($this)->getConnection();

    expect((int) $connection->fetchOne(
        'SELECT COUNT(*) FROM users WHERE email = ?',
        [mb_strtolower($email)],
    ))->toBe(1)
        ->and(authTestUserRepository($this)->findByEmail(Email::fromString($email)->value())?->id()->value())
        ->toBe($existing->id()->value());
});

it('requires email verification before login', function () {
    $email = 'unverified-login@example.com';
    authTestCreateUser($this, $email, verified: false);

    $blocked = authTestLogin($this, $email);

    expect($blocked->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['EMAIL_NOT_VERIFIED']);

    $user = authTestUserRepository($this)->findByEmail(Email::fromString($email)->value());
    assert(null !== $user);
    $user->verifyEmail();
    authTestUserRepository($this)->save($user);

    expect(authTestLogin($this, $email)->getStatusCode())->toBe(Response::HTTP_OK);
});

it('rejects invalid registration payload', function (array $overrides, array $expectedCodes) {
    $response = $this->api->post(
        route: 'app_auth_register',
        payload: RegisterUserRequestFactory::new($overrides)->create(),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe($expectedCodes);
})->with([
    'invalid email' => [fn () => ['email' => 'not-an-email'], ['INVALID_FORMAT']],
    'short password' => [fn () => ['password' => 'short'], ['STRING_TOO_SHORT']],
]);

it('translates Symfony Validator messages to pt-BR', function () {
    $response = $this->api->post(
        route: 'app_auth_register',
        payload: [
            'email' => faker()->email(),
            'name' => ['invalid type'],
            'password' => 'secret1234',
        ],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);

    /** @var array{errors: list<array{code: string, field?: string, message: string}>} $body */
    $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($body['errors'][0]['code'])->toBe('INVALID_TYPE')
        ->and($body['errors'][0]['field'])->toBe('name')
        ->and($body['errors'][0]['message'])->toBe('Este valor deve ser do tipo string.');
});
