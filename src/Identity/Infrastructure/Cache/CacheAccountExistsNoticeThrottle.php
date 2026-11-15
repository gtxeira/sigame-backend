<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Cache;

use App\Identity\Application\Service\AccountExistsNoticeThrottleInterface;
use App\Shared\Domain\ValueObject\Email;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Cooldown por e-mail do aviso "conta já existe".
 */
final readonly class CacheAccountExistsNoticeThrottle implements AccountExistsNoticeThrottleInterface
{
    private const string PREFIX = 'account-exists.';

    public function __construct(
        #[Autowire(service: 'cache.account_exists_notice')]
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        #[Autowire(env: 'int:ACCOUNT_EXISTS_NOTICE_COOLDOWN')]
        private int $cooldownSeconds = 900,
    ) {
    }

    public function reserve(Email $email): bool
    {
        if ($this->cooldownSeconds <= 0) {
            return true;
        }

        try {
            $item = $this->cache->getItem(self::PREFIX.hash('sha256', $email->value()));

            if ($item->isHit()) {
                return false;
            }

            $item->set(true);
            $item->expiresAfter($this->cooldownSeconds);
            $this->cache->save($item);

            return true;
        } catch (Throwable $exception) {
            // Fail-closed: sem cache, é melhor não enviar do que permitir spam.
            $this->logger->warning('Falha ao aplicar cooldown do aviso de conta existente', [
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
