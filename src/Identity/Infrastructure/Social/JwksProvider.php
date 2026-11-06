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
        private CacheInterface $cache,
        #[Autowire(env: 'int:JWKS_CACHE_TTL')]
        private int $cacheTtlSeconds = 3600,
    ) {
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    public function fetch(string $url, bool $forceRefresh = false): array
    {
        $key = self::cacheKey($url);

        if ($forceRefresh) {
            $this->cache->delete($key);
        }

        $jwks = $this->cache->get(
            $key,
            function (ItemInterface $item) use ($url): array {
                $item->expiresAfter($this->cacheTtlSeconds);

                /** @var array<string, mixed> $payload */
                $payload = $this->httpClient->request('GET', $url, [
                    'headers' => ['Accept' => 'application/json'],
                    'timeout' => 5,
                    'max_duration' => 10,
                ])->toArray();

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

    /**
     * Renova o JWKS respeitando um intervalo mínimo entre tentativas.
     *
     * @return array{keys: list<array<string, mixed>>}
     */
    public function refresh(string $url): array
    {
        $guardKey = 'jwks.refresh.'.hash('sha256', $url);

        /** @var bool|null $refreshedRecently */
        $refreshedRecently = $this->cache->get(
            $guardKey,
            static fn (): ?bool => null,
        );

        if (true === $refreshedRecently) {
            return $this->fetch($url);
        }

        $this->cache->get(
            $guardKey,
            static function (ItemInterface $item): bool {
                $item->expiresAfter(self::REFRESH_GUARD_TTL);

                return true;
            },
        );

        return $this->fetch($url, true);
    }

    private static function cacheKey(string $url): string
    {
        return 'jwks.'.hash('sha256', $url);
    }
}
