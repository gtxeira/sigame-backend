<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use OpenApi\Attributes as OA;

/**
 * Dados para o cliente iniciar a autorização no provedor.
 */
#[OA\Schema(description: 'Instruções para iniciar a autorização social.')]
final readonly class SocialStartResponse
{
    public function __construct(
        #[OA\Property(
            description: 'URL completa para redirecionar o navegador ao provedor.',
            example: 'https://accounts.google.com/o/oauth2/v2/auth?...',
        )]
        public string $authorizationUrl,

        #[OA\Property(description: 'State que deve ser preservado até o callback.', example: 'a1b2c3')]
        public string $state,

        #[OA\Property(description: 'Validade do fluxo em segundos.', example: 600)]
        public int $expiresIn,
    ) {
    }
}
