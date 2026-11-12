<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * @return array<string, string>
 */
function meModalitiesTestServer(FunctionalTestCase $test, string $email): array
{
    authTestCreateUser($test, $email);

    return ['HTTP_AUTHORIZATION' => 'Bearer '.authTestAccessToken(authTestLogin($test, $email))];
}

function meModalitiesErrorCode(Response $response): string
{
    return authTestBody($response)['errors'][0]['code'] ?? '';
}

it('replaces the modalities after onboarding without granting xp again', function () {
    $server = meModalitiesTestServer($this, 'modalities@example.com');

    $this->api->post('app_me_onboarding', [
        'modalities' => ['corrida', 'caminhada'],
        'primaryModality' => 'corrida',
    ], server: $server);

    $response = $this->api->patch('app_me_modalities_update', [
        'modalities' => ['ciclismo', 'corrida'],
        'primaryModality' => 'ciclismo',
    ], server: $server);

    $data = authTestBody($response)['data'];

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($data['modalities'])->toBe(['ciclismo', 'corrida'])
        ->and($data['primaryModality'])->toBe('ciclismo')
        ->and($data['level'])->toBe(2)
        ->and($data['onboardingStatus'])->toBe('COMPLETED');
});

it('keeps the same modalities without a duplicate key violation', function () {
    $server = meModalitiesTestServer($this, 'modalities-same@example.com');

    $this->api->post('app_me_onboarding', [
        'modalities' => ['corrida', 'caminhada'],
        'primaryModality' => 'corrida',
    ], server: $server);

    $response = $this->api->patch('app_me_modalities_update', [
        'modalities' => ['corrida', 'caminhada'],
        'primaryModality' => 'corrida',
    ], server: $server);

    $data = authTestBody($response)['data'];

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($data['modalities'])->toBe(['caminhada', 'corrida'])
        ->and($data['primaryModality'])->toBe('corrida');
});

it('removes modalities and clears the primary one', function () {
    $server = meModalitiesTestServer($this, 'modalities-remove@example.com');

    $this->api->post('app_me_onboarding', [
        'modalities' => ['corrida', 'caminhada'],
        'primaryModality' => 'corrida',
    ], server: $server);

    $response = $this->api->patch('app_me_modalities_update', [
        'modalities' => ['caminhada'],
    ], server: $server);

    $data = authTestBody($response)['data'];

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($data['modalities'])->toBe(['caminhada'])
        ->and($data['primaryModality'])->toBeNull();
});

it('rejects the change before onboarding is completed', function () {
    $server = meModalitiesTestServer($this, 'modalities-pending@example.com');

    $response = $this->api->patch('app_me_modalities_update', [
        'modalities' => ['corrida'],
    ], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and(meModalitiesErrorCode($response))->toBe('ONBOARDING_NOT_COMPLETED');
});

it('rejects an empty modality list', function () {
    $server = meModalitiesTestServer($this, 'modalities-empty@example.com');

    $this->api->post('app_me_onboarding', [
        'modalities' => ['corrida'],
    ], server: $server);

    $response = $this->api->patch('app_me_modalities_update', [
        'modalities' => [],
    ], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and(meModalitiesErrorCode($response))->toBe('ONBOARDING_NO_MODALITY_SELECTED');
});

it('rejects a primary modality outside the list', function () {
    $server = meModalitiesTestServer($this, 'modalities-primary@example.com');

    $this->api->post('app_me_onboarding', [
        'modalities' => ['corrida'],
    ], server: $server);

    $response = $this->api->patch('app_me_modalities_update', [
        'modalities' => ['corrida'],
        'primaryModality' => 'ciclismo',
    ], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and(meModalitiesErrorCode($response))->toBe('ONBOARDING_INVALID_PRIMARY_MODALITY');
});

it('rejects an unknown modality', function () {
    $server = meModalitiesTestServer($this, 'modalities-unknown@example.com');

    $this->api->post('app_me_onboarding', [
        'modalities' => ['corrida'],
    ], server: $server);

    $response = $this->api->patch('app_me_modalities_update', [
        'modalities' => ['natacao'],
    ], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and(meModalitiesErrorCode($response))->toBe('INVALID_CHOICE');
});
