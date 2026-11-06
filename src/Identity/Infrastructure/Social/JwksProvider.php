<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use UnexpectedValueException;

/**
 * Busca e cacheia JWKS de provedores OIDC.
 *
 * Um `kid` desconhecido pode indicar rotação de chaves; nesse caso o cache é
 * renovado, mas no máximo uma vez por minuto para não transformar tokens
 * inválidos em carga contra o provedor.
 */
final readonly class JwksProvider
{
    private const int REFRESH_GUARD_TTL = 60;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
        #[Autowire(env: 'int:JWKS_CACHE_TTL')]
        private int $cacheTtlSeconds = 3600,
    ) {
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    public function fetch(string $url, bool $forceRefresh = false): array
    {
        $item = $this->cache->getItem(self::cacheKey($url));

        if ($forceRefresh || !$item->isHit()) {
            /** @var array<string, mixed> $payload */
            $payload = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 5,
                'max_duration' => 10,
            ])->toArray();

            if (!isset($payload['keys']) || !is_array($payload['keys'])) {
                throw new UnexpectedValueException('JWKS inválido: membro "keys" ausente.');
            }

            $item->set($payload);
            $item->expiresAfter($this->cacheTtlSeconds);
            $this->cache->save($item);
        }

        /** @var array{keys: list<array<string, mixed>>} $jwks */
        $jwks = $item->get();

        return $jwks;
    }

    /**
     * Renova o JWKS respeitando um intervalo mínimo entre tentativas.
     *
     * @return array{keys: list<array<string, mixed>>}
     */
    public function refresh(string $url): array
    {
        $guard = $this->cache->getItem('jwks.refresh.'.hash('sha256', $url));

        if ($guard->isHit()) {
            return $this->fetch($url);
        }

        $guard->set(true);
        $guard->expiresAfter(self::REFRESH_GUARD_TTL);
        $this->cache->save($guard);

        return $this->fetch($url, forceRefresh: true);
    }

    private static function cacheKey(string $url): string
    {
        return 'jwks.'.hash('sha256', $url);
    }
}
