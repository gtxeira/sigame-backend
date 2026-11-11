<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Tests\FunctionalTestCase;

function apiRouteOf(FunctionalTestCase $test, string $route): string
{
    /** @var RouterInterface $router */
    $router = $test->api->getKernelBrowser()->getContainer()->get('router');

    return $router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_PATH);
}

/**
 * @param array<string, string> $server
 */
function rawRequest(FunctionalTestCase $test, string $method, string $uri, array $server = [], ?string $content = null): void
{
    $test->api->getKernelBrowser()->request($method, $uri, server: $server, content: $content);
}

it('returns 400 for a malformed json payload', function () {
    rawRequest(
        $this,
        'POST',
        apiRouteOf($this, 'app_auth_register'),
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        '{"cpf":',
    );

    $response = $this->api->getKernelBrowser()->getResponse();

    expect($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($this->api->errorCodes())->toBe(['BAD_REQUEST'])
        ->and((string) $response->getContent())->not->toContain('Request payload')
        ->and((string) $response->getContent())->not->toContain('NotEncodableValueException');
});

it('returns 415 for an unsupported content type', function () {
    rawRequest(
        $this,
        'POST',
        apiRouteOf($this, 'app_auth_register'),
        ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'],
        '{}',
    );

    $response = $this->api->getKernelBrowser()->getResponse();

    expect($response->getStatusCode())->toBe(Response::HTTP_UNSUPPORTED_MEDIA_TYPE)
        ->and($this->api->errorCodes())->toBe(['UNSUPPORTED_MEDIA_TYPE'])
        ->and((string) $response->getContent())->not->toContain('Unsupported format')
        ->and((string) $response->getContent())->not->toContain('UnsupportedFormatException');
});

it('returns 405 for a method not allowed on the route', function () {
    rawRequest(
        $this,
        'GET',
        apiRouteOf($this, 'app_auth_register'),
        ['HTTP_ACCEPT' => 'application/json'],
    );

    $response = $this->api->getKernelBrowser()->getResponse();

    expect($response->getStatusCode())->toBe(Response::HTTP_METHOD_NOT_ALLOWED)
        ->and($this->api->errorCodes())->toBe(['METHOD_NOT_ALLOWED']);
});

it('returns 404 for an unknown api route', function () {
    rawRequest($this, 'GET', '/api/v1/does-not-exist', ['HTTP_ACCEPT' => 'application/json']);

    $response = $this->api->getKernelBrowser()->getResponse();

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($this->api->errorCodes())->toBe(['NOT_FOUND']);
});

it('keeps framework details out of validation errors', function () {
    rawRequest(
        $this,
        'POST',
        apiRouteOf($this, 'app_auth_register'),
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        '{}',
    );

    $response = $this->api->getKernelBrowser()->getResponse();

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['FIELD_REQUIRED', 'FIELD_REQUIRED', 'FIELD_REQUIRED'])
        ->and((string) $response->getContent())->not->toContain('Symfony\\');
});
