<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Cache;

use App\Identity\Application\Service\PasswordResetThrottleInterface;
use App\Shared\Domain\ValueObject\Email;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Cooldown de pedidos de reset guardado no cache, indexado pelo hash do e-mail.
 *
 * Guardar estado mesmo para e-mails sem conta é o que torna o `Retry-After`
 * indistinguível entre contas existentes e inexistentes.
 */
final readonly class CachePasswordResetThrottle implements PasswordResetThrottleInterface
{
    private const string PREFIX = 'password-reset.';

    public function __construct(
        #[Autowire(service: 'cache.password_reset_throttle')]
        private CacheItemPoolInterface $cache,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire(env: 'int:PASSWORD_RESET_COOLDOWN')]
        private int $cooldownSeconds = 60,
    ) {
    }

    public function reserve(Email $email): ?int
    {
        if ($this->cooldownSeconds <= 0) {
            return null;
        }

        try {
            $key = self::PREFIX.hash('sha256', $email->value());
            $now = $this->clock->now()->getTimestamp();
            $item = $this->cache->getItem($key);

            if ($item->isHit()) {
                $remaining = $this->cooldownSeconds - ($now - (int) $item->get());

                if ($remaining > 0) {
                    return $remaining;
                }
            }

            $item->set($now);
            $item->expiresAfter($this->cooldownSeconds);
            $this->cache->save($item);

            return null;
        } catch (Throwable $exception) {
            // Falha de cache não pode negar o pedido; sem header, a resposta
            // continua uniforme entre contas existentes e inexistentes.
            $this->logger->warning('Falha ao aplicar cooldown de reset de senha', [
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
