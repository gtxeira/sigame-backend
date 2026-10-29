<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

it('reports liveness', function () {
    $response = $this->api->get(route: 'app_health_liveness');

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and((string) $response->getContent())->toContain('"status":"UP"');
});

it('reports readiness with database connectivity', function () {
    $response = $this->api->get(route: 'app_health_readiness');

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and((string) $response->getContent())->toContain('"status":"HEALTHY"')
        ->and((string) $response->getContent())->toContain('"database":"UP"');
});
