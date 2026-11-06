<?php

declare(strict_types=1);

use App\Identity\Application\DTO\LinkTicket;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Identity\Infrastructure\Social\CacheSocialLinkTicketStore;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

function cacheTicketTestTicket(string $email = 'ticket@example.com'): LinkTicket
{
    return new LinkTicket(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google:'.$email),
        email: Email::fromString($email)->value(),
        expiresAt: Instant::fromDateTime(new DateTimeImmutable('2026-01-01T00:10:00+00:00')),
    );
}

it('stores and consumes a link ticket exactly once', function () {
    $store = new CacheSocialLinkTicketStore(new ArrayAdapter());
    $ticket = cacheTicketTestTicket();

    $store->save('raw-ticket', $ticket, 600);

    $first = $store->consume('raw-ticket');
    $second = $store->consume('raw-ticket');

    expect($first)->toBeInstanceOf(LinkTicket::class)
        ->and($first?->email->value())->toBe('ticket@example.com')
        ->and($second)->toBeNull();
});

it('returns null for an unknown ticket', function () {
    expect((new CacheSocialLinkTicketStore(new ArrayAdapter()))->consume('unknown'))->toBeNull();
});

it('indexes the ticket by the token hash and asks for the configured ttl', function () {
    $item = Mockery::mock(CacheItemInterface::class);
    $item->shouldReceive('set')->once()->with(Mockery::type(LinkTicket::class))->andReturnSelf();
    $item->shouldReceive('expiresAfter')->once()->with(600)->andReturnSelf();

    $cache = Mockery::mock(CacheItemPoolInterface::class);
    $cache->shouldReceive('getItem')
        ->once()
        ->with('oauth.link.'.hash('sha256', 'raw-ticket'))
        ->andReturn($item);
    $cache->shouldReceive('save')->once()->with($item)->andReturnTrue();

    (new CacheSocialLinkTicketStore($cache))->save('raw-ticket', cacheTicketTestTicket(), 600);
});

it('is not readable from a different raw token', function () {
    $store = new CacheSocialLinkTicketStore(new ArrayAdapter());

    $store->save('raw-ticket', cacheTicketTestTicket(), 600);

    expect($store->consume('other-ticket'))->toBeNull()
        ->and($store->consume('raw-ticket'))->not->toBeNull();
});
