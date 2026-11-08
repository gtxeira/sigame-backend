<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function onboardingTestPayload(array $overrides = []): array
{
    return array_merge([
        'heightCm' => 178,
        'weightKg' => 75.5,
        'photoUrl' => 'https://example.com/photo.jpg',
        'modalities' => ['corrida', 'caminhada'],
        'primaryModality' => 'corrida',
    ], $overrides);
}

it('completes onboarding and exposes the profile', function () {
    authTestCreateUser($this, 'onboarding@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'onboarding@example.com'));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $response = $this->api->post('app_me_onboarding', onboardingTestPayload(), server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($response)['data'];

    expect($data['level'])->toBe(2)
        ->and($data['onboardingStatus'])->toBe('COMPLETED')
        ->and($data['heightCm'])->toBe(178)
        ->and($data['weightKg'])->toEqual(75.5)
        ->and($data['photoUrl'])->toBe('https://example.com/photo.jpg')
        ->and($data['modalities'])->toBe(['caminhada', 'corrida'])
        ->and($data['primaryModality'])->toBe('corrida');

    $me = $this->api->get('app_me', server: $server);
    $meData = authTestBody($me)['data'];

    expect($me->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($meData['level'])->toBe(2)
        ->and($meData['heightCm'])->toBe(178)
        ->and($meData['modalities'])->toBe(['caminhada', 'corrida']);
});

it('completes onboarding without optional profile data', function () {
    authTestCreateUser($this, 'onboarding-minimal@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'onboarding-minimal@example.com'));

    $response = $this->api->post('app_me_onboarding', [
        'modalities' => ['ciclismo'],
    ], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken]);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($response)['data'];

    expect($data['heightCm'])->toBeNull()
        ->and($data['weightKg'])->toBeNull()
        ->and($data['photoUrl'])->toBeNull()
        ->and($data['modalities'])->toBe(['ciclismo'])
        ->and($data['primaryModality'])->toBeNull();
});

it('rejects a second onboarding with conflict', function () {
    authTestCreateUser($this, 'onboarding-twice@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'onboarding-twice@example.com'));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $this->api->post('app_me_onboarding', onboardingTestPayload(), server: $server);

    $response = $this->api->post('app_me_onboarding', onboardingTestPayload(), server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['ONBOARDING_ALREADY_COMPLETED']);
});

it('rejects onboarding data violating business rules', function (array $payload, string $expectedCode) {
    authTestCreateUser($this, 'onboarding-invalid@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'onboarding-invalid@example.com'));

    $response = $this->api->post('app_me_onboarding', $payload, server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe([$expectedCode]);
})->with([
    'height below the minimum' => [
        ['heightCm' => 49, 'modalities' => ['corrida']],
        'USER_HEIGHT_INVALID',
    ],
    'height above the maximum' => [
        ['heightCm' => 251, 'modalities' => ['corrida']],
        'USER_HEIGHT_INVALID',
    ],
    'weight below the minimum' => [
        ['weightKg' => 19.9, 'modalities' => ['corrida']],
        'USER_WEIGHT_INVALID',
    ],
    'weight above the maximum' => [
        ['weightKg' => 300.1, 'modalities' => ['corrida']],
        'USER_WEIGHT_INVALID',
    ],
    'no modality selected' => [
        ['modalities' => []],
        'ONBOARDING_NO_MODALITY_SELECTED',
    ],
    'primary modality not selected' => [
        ['modalities' => ['corrida'], 'primaryModality' => 'ciclismo'],
        'ONBOARDING_INVALID_PRIMARY_MODALITY',
    ],
]);

it('rejects an unknown modality', function () {
    authTestCreateUser($this, 'onboarding-unknown@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'onboarding-unknown@example.com'));

    $response = $this->api->post('app_me_onboarding', [
        'modalities' => ['natação'],
    ], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken]);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);
});

it('rate limits repeated onboarding attempts', function () {
    authTestCreateUser($this, 'onboarding-rate@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'onboarding-rate@example.com'));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    for ($attempt = 1; $attempt <= 10; ++$attempt) {
        $response = $this->api->post('app_me_onboarding', ['modalities' => []], server: $server);
        expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $response = $this->api->post('app_me_onboarding', ['modalities' => []], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});
