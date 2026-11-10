<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

/**
 * Porta de emissão da autorização de assinatura do stream.
 */
interface StreamAuthorizationProviderInterface
{
    public function authorize(string $userId): RealtimeAuthorization;
}
