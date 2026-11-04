<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use UnexpectedValueException;

/**
 * Busca e cacheia JWKS de provedores OIDC.
 */
final readonly class JwksProvider
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        #[Autowire('%env(int:JWKS_CACHE_TTL)%')]
        private int $cacheTtlSeconds = 3600,
    ) {
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    public function fetch(string $url): array
    {
        $jwks = $this->cache->get(
            'jwks.'.hash('sha256', $url),
            function (ItemInterface $item) use ($url): array {
                $item->expiresAfter($this->cacheTtlSeconds);

                /** @var array<string, mixed> $payload */
                $payload = $this->httpClient->request('GET', $url)->toArray();

                if (!isset($payload['keys']) || !is_array($payload['keys'])) {
                    throw new UnexpectedValueException('JWKS inválido: membro "keys" ausente.');
                }

                return $payload;
            },
        );

        /** @var array{keys: list<array<string, mixed>>} $jwks */
        $jwks = $jwks;

        return $jwks;
    }
}
