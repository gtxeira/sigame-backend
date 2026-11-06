<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use App\Identity\Application\DTO\SocialFlow;
use App\Identity\Application\Service\SocialFlowStoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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
        private CacheInterface $cache,
    ) {
    }

    public function save(SocialFlow $flow, int $ttlSeconds): void
    {
        $this->cache->get(
            self::PREFIX.$flow->id,
            static function (ItemInterface $item) use ($flow, $ttlSeconds): SocialFlow {
                $item->expiresAfter($ttlSeconds);

                return $flow;
            },
        );
    }

    public function consume(string $flowId): ?SocialFlow
    {
        $key = self::PREFIX.$flowId;

        /** @var SocialFlow|null $flow */
        $flow = $this->cache->get($key, static fn (): ?SocialFlow => null);

        if (null === $flow) {
            return null;
        }

        $this->cache->delete($key);

        return $flow;
    }

    public function delete(string $flowId): void
    {
        $this->cache->delete(self::PREFIX.$flowId);
    }
}
