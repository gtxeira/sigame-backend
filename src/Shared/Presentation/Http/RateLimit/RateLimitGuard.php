<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\RateLimit;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Aplica rate limiting a uma requisição, respondendo 429 quando o limite é atingido.
 */
final class RateLimitGuard
{
    public function assertWithin(RateLimiterFactoryInterface $limiter, string $key): void
    {
        $limit = $limiter->create($key)->consume(1);

        if ($limit->isAccepted()) {
            return;
        }

        throw new TooManyRequestsHttpException(
            retryAfter: max(1, $limit->getRetryAfter()->getTimestamp() - time()),
            headers: [
                'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
                'X-RateLimit-Limit' => (string) $limit->getLimit(),
            ],
        );
    }
}
