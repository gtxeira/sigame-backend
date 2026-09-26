<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Tests\Helpers\DatabaseCleaner;

beforeEach(function () {
    $this->client = $this->createClient();
    DatabaseCleaner::truncateAll($this->client->getKernel());
});

/**
 * Envia um POST de registro e retorna a resposta.
 *
 * @param array<string, mixed> $payload
 */
function postRegistration(KernelBrowser $client, array $payload): Response
{
    $client->request('POST', '/api/v1/auth/register', [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode($payload, JSON_THROW_ON_ERROR));

    return $client->getResponse();
}

it('returns 201 created with valid data', function () {
    $response = postRegistration($this->client, [
        'cpf' => '52998224725',
        'email' => uniqid('test_', true).'@example.com',
        'name' => 'João Silva',
        'password' => 'secret1234',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($data)->toHaveKey('data')
        ->and($data['data'])->toHaveKey('id')
        ->and($data['data']['name'])->toBe('João Silva')
        ->and($data['data']['level'])->toBe(1)
        ->and($data['data']['status'])->toBe('ACTIVE');
});

it('rejects duplicate email', function () {
    $email = uniqid('dup_', true).'@example.com';

    // First registration
    $response = postRegistration($this->client, [
        'cpf' => '52998224725',
        'email' => $email,
        'name' => 'First User',
        'password' => 'secret1234',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    // Second registration with same email
    $response = postRegistration($this->client, [
        'cpf' => '40584042090',
        'email' => $email,
        'name' => 'Second User',
        'password' => 'secret1234',
    ]);

    expect($response->getStatusCode())->not->toBe(Response::HTTP_CREATED);
});

it('rejects invalid registration payload', function (array $overrides) {
    $response = postRegistration($this->client, array_merge([
        'cpf' => '52998224725',
        'email' => uniqid('test_', true).'@example.com',
        'name' => 'João Silva',
        'password' => 'secret1234',
    ], $overrides));

    expect($response->getStatusCode())->not->toBe(Response::HTTP_CREATED);
})->with([
    'invalid cpf' => [['cpf' => '123']],
    'short password' => [['password' => '123']],
]);
