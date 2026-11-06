<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use App\Identity\Application\DTO\LinkTicket;
use App\Identity\Application\Service\SocialLinkTicketStoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Guarda o ticket de vínculo no cache, indexado pelo hash do token.
 */
final readonly class CacheSocialLinkTicketStore implements SocialLinkTicketStoreInterface
{
    private const string PREFIX = 'oauth.link.';

    public function __construct(
        #[Autowire(service: 'cache.social_flow')]
        private CacheInterface $cache,
    ) {
    }

    public function save(string $token, LinkTicket $ticket, int $ttlSeconds): void
    {
        $this->cache->get(
            self::key($token),
            static function (ItemInterface $item) use ($ticket, $ttlSeconds): LinkTicket {
                $item->expiresAfter($ttlSeconds);

                return $ticket;
            },
        );
    }

    public function consume(string $token): ?LinkTicket
    {
        $key = self::key($token);

        /** @var LinkTicket|null $ticket */
        $ticket = $this->cache->get($key, static fn (): ?LinkTicket => null);

        if (null === $ticket) {
            return null;
        }

        $this->cache->delete($key);

        return $ticket;
    }

    private static function key(string $token): string
    {
        return self::PREFIX.hash('sha256', $token);
    }
}
