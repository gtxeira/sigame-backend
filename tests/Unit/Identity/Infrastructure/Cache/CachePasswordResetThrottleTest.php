<?php

declare(strict_types=1);

use App\Identity\Infrastructure\Cache\CachePasswordResetThrottle;
use App\Shared\Domain\ValueObject\Email;
use Mockery\MockInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

function passwordResetThrottleKey(string $email): string
{
    return 'password-reset.'.hash('sha256', $email);
}

/**
 * @return CacheItemInterface&MockInterface
 */
function passwordResetThrottleItem(
    bool $hit,
    ?int $requestedAt = null,
): CacheItemInterface {
    $item = Mockery::mock(CacheItemInterface::class);
    $item->shouldReceive('isHit')->andReturn($hit);
    $item->shouldReceive('get')->andReturn($requestedAt);

    return $item;
}

it('reserves the window and returns null on a miss', function () {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $email = Email::fromString('user@example.com')->value();
    $item = passwordResetThrottleItem(false);

    $item->shouldReceive('set')->once()->with($now->getTimestamp())->andReturnSelf();
    $item->shouldReceive('expiresAfter')->once()->with(60)->andReturnSelf();

    $cache = Mockery::mock(CacheItemPoolInterface::class);
    $cache->shouldReceive('getItem')
        ->once()
        ->with(passwordResetThrottleKey('user@example.com'))
        ->andReturn($item);
    $cache->shouldReceive('save')->once()->with($item)->andReturn(true);

    $throttle = new CachePasswordResetThrottle(
        cache: $cache,
        clock: new MockClock($now),
        logger: Mockery::mock(LoggerInterface::class),
        cooldownSeconds: 60,
    );

    expect($throttle->reserve($email))->toBeNull();
});

it('returns the remaining seconds when the window is still open', function () {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $item = passwordResetThrottleItem(true, $now->getTimestamp() - 30);

    $cache = Mockery::mock(CacheItemPoolInterface::class);
    $cache->shouldReceive('getItem')->once()->andReturn($item);
    $cache->shouldNotReceive('save');

    $throttle = new CachePasswordResetThrottle(
        cache: $cache,
        clock: new MockClock($now),
        logger: Mockery::mock(LoggerInterface::class),
        cooldownSeconds: 60,
    );

    expect($throttle->reserve(Email::fromString('user@example.com')->value()))->toBe(30);
});

it('reserves again after the window has elapsed', function () {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $item = passwordResetThrottleItem(true, $now->getTimestamp() - 60);

    $item->shouldReceive('set')->once()->with($now->getTimestamp())->andReturnSelf();
    $item->shouldReceive('expiresAfter')->once()->with(60)->andReturnSelf();

    $cache = Mockery::mock(CacheItemPoolInterface::class);
    $cache->shouldReceive('getItem')->once()->andReturn($item);
    $cache->shouldReceive('save')->once()->andReturn(true);

    $throttle = new CachePasswordResetThrottle(
        cache: $cache,
        clock: new MockClock($now),
        logger: Mockery::mock(LoggerInterface::class),
        cooldownSeconds: 60,
    );

    expect($throttle->reserve(Email::fromString('user@example.com')->value()))->toBeNull();
});

it('skips the cache when the cooldown is disabled', function () {
    $cache = Mockery::mock(CacheItemPoolInterface::class);
    $cache->shouldNotReceive('getItem');

    $throttle = new CachePasswordResetThrottle(
        cache: $cache,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        logger: Mockery::mock(LoggerInterface::class),
        cooldownSeconds: 0,
    );

    expect($throttle->reserve(Email::fromString('user@example.com')->value()))->toBeNull();
});

it('fails open and logs when the cache is unavailable', function () {
    $cache = Mockery::mock(CacheItemPoolInterface::class);
    $cache->shouldReceive('getItem')->once()->andThrow(new RuntimeException('cache down'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha ao aplicar cooldown de reset de senha', Mockery::on(
            static fn (array $context): bool => 'cache down' === $context['reason'],
        ));

    $throttle = new CachePasswordResetThrottle(
        cache: $cache,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        logger: $logger,
        cooldownSeconds: 60,
    );

    expect($throttle->reserve(Email::fromString('user@example.com')->value()))->toBeNull();
});
