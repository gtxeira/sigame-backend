<?php

declare(strict_types=1);

namespace App\Notifications\Presentation\Http\Response;

use App\Notifications\Application\Service\RealtimeAuthorization;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Autorização de assinatura do stream de notificações.')]
final readonly class RealtimeAuthorizationResponse
{
    public function __construct(
        #[OA\Property(example: 'mercure')]
        public string $transport,

        #[OA\Property(example: 'https://api.sigame.localhost:8443/.well-known/mercure?topic=...')]
        public string $subscribeUrl,

        #[OA\Property(description: 'Token de assinatura somente leitura; envie no header Authorization do SSE.')]
        public string $token,

        #[OA\Property(format: 'date-time', example: '2026-11-10T10:15:00.000+00:00')]
        public string $expiresAt,
    ) {
    }

    public static function fromAuthorization(RealtimeAuthorization $authorization): self
    {
        return new self(
            transport: $authorization->transport,
            subscribeUrl: $authorization->subscribeUrl,
            token: $authorization->token,
            expiresAt: $authorization->expiresAt,
        );
    }
}
