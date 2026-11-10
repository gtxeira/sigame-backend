<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

/**
 * Autorização de assinatura do stream de notificações.
 *
 * O transporte é abstraído para que web, app nativo e futuros clientes usem o
 * mesmo contrato.
 */
final readonly class RealtimeAuthorization
{
    public function __construct(
        public string $transport,
        public string $subscribeUrl,
        public string $token,
        public string $expiresAt,
    ) {
    }
}
