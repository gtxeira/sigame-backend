<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use App\Identity\Application\DTO\SocialFlow;
use App\Identity\Application\Service\SocialFlowStoreInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Guarda o fluxo OAuth no cache até o callback.
 *
 * O valor fica no servidor; o cliente recebe apenas o id opaco no cookie.
 */
final readonly class CacheSocialFlowStore implements SocialFlowStoreInterface
{
    private const string PREFIX = 'oauth.flow.';

    public function __construct(
        #[Autowire(service: 'cache.social_flow')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function save(SocialFlow $flow, int $ttlSeconds): void
    {
        $item = $this->cache->getItem(self::PREFIX.$flow->id);
        $item->set($flow);
        $item->expiresAfter($ttlSeconds);
        $this->cache->save($item);
    }

    public function consume(string $flowId): ?SocialFlow
    {
        $key = self::PREFIX.$flowId;
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $this->cache->deleteItem($key);

        $flow = $item->get();

        return $flow instanceof SocialFlow ? $flow : null;
    }

    public function delete(string $flowId): void
    {
        $this->cache->deleteItem(self::PREFIX.$flowId);
    }
}
