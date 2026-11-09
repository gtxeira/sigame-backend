<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Domain\User\ValueObject\Weight;
use App\Identity\Presentation\Http\Response\CurrentUserResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * @return array{0: \App\Identity\Domain\User\Entity\User, 1: array<string, mixed>}
 */
function meProfileTestUser(
    FunctionalTestCase $test,
    string $email = 'profile@example.com',
    bool $withPhysicalData = true,
): array {
    $user = authTestCreateUser($test, $email);

    if ($withPhysicalData) {
        $user->changeProfile(
            $user->profile()
                ->withHeight(Height::fromCentimeters(165)->value())
                ->withWeight(Weight::fromKilograms(62.5)->value()),
        );
        $user->changePhoto('users/profile/photo.png');
        authTestUserRepository($test)->save($user);
    }

    $accessToken = authTestAccessToken(authTestLogin($test, $email));

    return [$user, ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken]];
}

it('updates only the name preserving the remaining profile fields', function () {
    [$user, $server] = meProfileTestUser($this);

    $response = $this->api->patch('app_me_profile_update', ['name' => '  Maria Silva  '], server: $server);

    $me = $this->api->deserializeResponse(CurrentUserResponse::class);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($me->name)->toBe('Maria Silva')
        ->and($me->photoUrl)->toBe('http://cdn.test/users/profile/photo.png');

    $profile = authTestUserRepository($this)->findById($user->id());

    assert(null !== $profile);

    expect($profile->name())->toBe('Maria Silva')
        ->and($profile->height()?->centimeters())->toBe(165)
        ->and($profile->weight()?->kilograms())->toBe(62.5)
        ->and($profile->photoKey())->toBe('users/profile/photo.png');
});

it('updates only the height when the field is present', function () {
    [$user, $server] = meProfileTestUser($this);

    $response = $this->api->patch('app_me_profile_update', ['heightCm' => 178], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $profile = authTestUserRepository($this)->findById($user->id());

    assert(null !== $profile);

    expect($profile->height()?->centimeters())->toBe(178)
        ->and($profile->weight()?->kilograms())->toBe(62.5)
        ->and($profile->name())->toBe('Auth Test User');
});

it('updates only the weight when the field is present', function () {
    [$user, $server] = meProfileTestUser($this);

    $response = $this->api->patch('app_me_profile_update', ['weightKg' => 70.2], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $profile = authTestUserRepository($this)->findById($user->id());

    assert(null !== $profile);

    expect($profile->weight()?->kilograms())->toBe(70.2)
        ->and($profile->height()?->centimeters())->toBe(165);
});

it('clears the height when the field is explicitly null', function () {
    [$user, $server] = meProfileTestUser($this);

    $response = $this->api->patch('app_me_profile_update', ['heightCm' => null], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $profile = authTestUserRepository($this)->findById($user->id());

    assert(null !== $profile);

    expect($profile->height())->toBeNull()
        ->and($profile->weight()?->kilograms())->toBe(62.5);
});

it('clears the weight when the field is explicitly null', function () {
    [$user, $server] = meProfileTestUser($this);

    $response = $this->api->patch('app_me_profile_update', ['weightKg' => null], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $profile = authTestUserRepository($this)->findById($user->id());

    assert(null !== $profile);

    expect($profile->weight())->toBeNull()
        ->and($profile->height()?->centimeters())->toBe(165);
});

it('clears both physical fields in a single request', function () {
    [$user, $server] = meProfileTestUser($this);

    $response = $this->api->patch(
        'app_me_profile_update',
        ['heightCm' => null, 'weightKg' => null],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $profile = authTestUserRepository($this)->findById($user->id());

    assert(null !== $profile);

    expect($profile->height())->toBeNull()
        ->and($profile->weight())->toBeNull()
        ->and($profile->photoKey())->toBe('users/profile/photo.png');
});

it('rejects a request without any known field', function (array $payload, string $field) {
    [, $server] = meProfileTestUser($this);

    $response = $this->api->patch('app_me_profile_update', $payload, server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);

    $error = authTestBody($response)['errors'][0];

    expect($error['details'][0]['field'])->toBe($field);
})->with([
    'empty body' => [[], 'body'],
    'unknown fields only' => [['nickname' => 'maria'], 'body'],
    'null name' => [['name' => null], 'name'],
    'blank name' => [['name' => '   '], 'name'],
    'short name' => [['name' => 'a'], 'name'],
    'numeric name' => [['name' => 123], 'name'],
]);

it('rejects physically invalid values with business codes', function (array $payload, string $code) {
    [$user, $server] = meProfileTestUser($this);

    $response = $this->api->patch('app_me_profile_update', $payload, server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe([$code]);

    $profile = authTestUserRepository($this)->findById($user->id());

    assert(null !== $profile);

    expect($profile->height()?->centimeters())->toBe(165)
        ->and($profile->weight()?->kilograms())->toBe(62.5);
})->with([
    'height below the minimum' => [['heightCm' => 49], 'USER_HEIGHT_INVALID'],
    'height above the maximum' => [['heightCm' => 251], 'USER_HEIGHT_INVALID'],
    'weight below the minimum' => [['weightKg' => 19.9], 'USER_WEIGHT_INVALID'],
    'weight above the maximum' => [['weightKg' => 300.1], 'USER_WEIGHT_INVALID'],
]);

it('rejects a value with an invalid type', function () {
    [, $server] = meProfileTestUser($this);

    $response = $this->api->patch('app_me_profile_update', ['heightCm' => 'x'], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);
});

it('rejects a partial update without a bearer token', function () {
    $response = $this->api->patch('app_me_profile_update', ['name' => 'Maria']);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['ACCESS_TOKEN_MISSING']);
});

it('rate limits repeated profile updates', function () {
    [, $server] = meProfileTestUser($this);

    for ($attempt = 1; $attempt <= 10; ++$attempt) {
        $response = $this->api->patch('app_me_profile_update', ['name' => 'Maria Silva'], server: $server);
        expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    }

    $response = $this->api->patch('app_me_profile_update', ['name' => 'Maria Silva'], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});
