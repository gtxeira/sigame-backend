<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use Tests\Helpers\DatabaseCleaner;

beforeEach(function () {
    $this->client = static::createClient();
    DatabaseCleaner::truncateAll($this->client->getKernel());
});

it('returns 201 created with valid data', function () {
    $this->client->request('POST', '/api/v1/auth/register', [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode([
        'cpf' => '52998224725',
        'email' => uniqid('test_', true).'@example.com',
        'name' => 'João Silva',
        'password' => 'secret1234',
    ], JSON_THROW_ON_ERROR));

    $response = $this->client->getResponse();

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
    $this->client->request('POST', '/api/v1/auth/register', [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode([
        'cpf' => '52998224725',
        'email' => $email,
        'name' => 'First User',
        'password' => 'secret1234',
    ], JSON_THROW_ON_ERROR));

    expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED);

    // Second registration with same email
    $this->client->request('POST', '/api/v1/auth/register', [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode([
        'cpf' => '40584042090',
        'email' => $email,
        'name' => 'Second User',
        'password' => 'secret1234',
    ], JSON_THROW_ON_ERROR));

    expect($this->client->getResponse()->getStatusCode())->not->toBe(Response::HTTP_CREATED);
});

it('rejects invalid cpf', function () {
    $this->client->request('POST', '/api/v1/auth/register', [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode([
        'cpf' => '123',
        'email' => uniqid('test_', true).'@example.com',
        'name' => 'João Silva',
        'password' => 'secret1234',
    ], JSON_THROW_ON_ERROR));

    expect($this->client->getResponse()->getStatusCode())->not->toBe(Response::HTTP_CREATED);
});

it('rejects short password', function () {
    $this->client->request('POST', '/api/v1/auth/register', [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode([
        'cpf' => '52998224725',
        'email' => uniqid('test_', true).'@example.com',
        'name' => 'João Silva',
        'password' => '123',
    ], JSON_THROW_ON_ERROR));

    expect($this->client->getResponse()->getStatusCode())->not->toBe(Response::HTTP_CREATED);
});
