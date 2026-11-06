<?php

declare(strict_types=1);

use App\Identity\Application\DTO\SocialFlow;
use App\Identity\Application\DTO\SocialFlowPurpose;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Infrastructure\Social\CacheSocialFlowStore;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

function cacheFlowTestFlow(string $id = 'flow-id'): SocialFlow
{
    return new SocialFlow(
        id: $id,
        provider: IdentityProvider::GOOGLE,
        client: 'web',
        redirectUri: 'https://app.example.com/auth/google/callback',
        codeVerifier: 'verifier',
        purpose: SocialFlowPurpose::Login,
        userId: null,
        state: 'state',
        expiresAt: Instant::fromDateTime(new DateTimeImmutable('2026-01-01T00:10:00+00:00')),
    );
}

it('stores and consumes a flow exactly once', function () {
    $store = new CacheSocialFlowStore(new ArrayAdapter());
    $flow = cacheFlowTestFlow();

    $store->save($flow, 600);

    $first = $store->consume($flow->id);
    $second = $store->consume($flow->id);

    expect($first)->toBeInstanceOf(SocialFlow::class)
        ->and($first?->id)->toBe('flow-id')
        ->and($second)->toBeNull();
});

it('returns null for an unknown flow', function () {
    expect((new CacheSocialFlowStore(new ArrayAdapter()))->consume('unknown'))->toBeNull();
});

it('asks the pool for the configured ttl', function () {
    $item = Mockery::mock(CacheItemInterface::class);
    $item->shouldReceive('set')->once()->with(Mockery::type(SocialFlow::class))->andReturnSelf();
    $item->shouldReceive('expiresAfter')->once()->with(600)->andReturnSelf();

    $cache = Mockery::mock(CacheItemPoolInterface::class);
    $cache->shouldReceive('getItem')->once()->with('oauth.flow.flow-id')->andReturn($item);
    $cache->shouldReceive('save')->once()->with($item)->andReturnTrue();

    (new CacheSocialFlowStore($cache))->save(cacheFlowTestFlow(), 600);
});

it('deletes a flow without consuming it', function () {
    $store = new CacheSocialFlowStore(new ArrayAdapter());
    $flow = cacheFlowTestFlow();

    $store->save($flow, 600);
    $store->delete($flow->id);

    expect($store->consume($flow->id))->toBeNull();
});
