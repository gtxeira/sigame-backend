<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use App\Identity\Application\DTO\LinkTicket;
use App\Identity\Application\Service\SocialLinkTicketStoreInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Guarda o ticket de vínculo no cache, indexado pelo hash do token.
 */
final readonly class CacheSocialLinkTicketStore implements SocialLinkTicketStoreInterface
{
    private const string PREFIX = 'oauth.link.';

    public function __construct(
        #[Autowire(service: 'cache.social_flow')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function save(string $token, LinkTicket $ticket, int $ttlSeconds): void
    {
        $item = $this->cache->getItem(self::key($token));
        $item->set($ticket);
        $item->expiresAfter($ttlSeconds);
        $this->cache->save($item);
    }

    public function consume(string $token): ?LinkTicket
    {
        $key = self::key($token);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $this->cache->deleteItem($key);

        $ticket = $item->get();

        return $ticket instanceof LinkTicket ? $ticket : null;
    }

    private static function key(string $token): string
    {
        return self::PREFIX.hash('sha256', $token);
    }
}
