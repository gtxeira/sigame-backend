<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Notifications\Application\Service\RealtimeAuthorization;
use App\Notifications\Application\Service\StreamAuthorizationProviderInterface;

/**
 * Autorização de stream determinística para testes.
 */
final class FakeStreamAuthorizationProvider implements StreamAuthorizationProviderInterface
{
    public function authorize(string $userId): RealtimeAuthorization
    {
        return new RealtimeAuthorization(
            transport: 'mercure',
            subscribeUrl: 'https://mercure.test/.well-known/mercure?topic='.rawurlencode('https://mercure.test/notifications/'.$userId),
            token: 'test-token-'.$userId,
            expiresAt: '2026-11-10T10:15:00.000+00:00',
        );
    }
}
