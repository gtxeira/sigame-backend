<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Infrastructure\Mercure;

use App\Notifications\Infrastructure\Mercure\MercureStreamAuthorizationProvider;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Mockery as m;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\ProtocolVersion;

it('mints a read-only legacy token scoped to the user topic', function () {
    $hub = m::mock(HubInterface::class);
    $hub->shouldReceive('getProtocolVersion')->andReturn(ProtocolVersion::Legacy);
    $hub->shouldReceive('getPublicUrl')->andReturn('https://mercure.test/.well-known/mercure');

    $secret = 'test-secret-with-at-least-32-characters';
    $provider = new MercureStreamAuthorizationProvider(
        hub: $hub,
        secret: $secret,
        topicBase: 'https://mercure.test/notifications',
        tokenTtl: 900,
    );

    $authorization = $provider->authorize('user-1');
    $decoded = (array) JWT::decode($authorization->token, new Key($secret, 'HS256'));

    expect($authorization->transport)->toBe('mercure')
        ->and($authorization->subscribeUrl)->toBe('https://mercure.test/.well-known/mercure?topic='.rawurlencode('https://mercure.test/notifications/user-1'))
        ->and((array) $decoded['mercure'])->toBe(['subscribe' => ['https://mercure.test/notifications/user-1']])
        ->and($decoded)->not->toHaveKey('publish');
});
