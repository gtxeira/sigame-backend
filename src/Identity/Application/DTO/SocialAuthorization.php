<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

/**
 * Dados devolvidos ao cliente para iniciar a autorização no provedor.
 *
 * `flowId` é usado pelo controller para o cookie HttpOnly e não vai no corpo.
 */
final readonly class SocialAuthorization
{
    public function __construct(
        public string $flowId,
        public string $authorizationUrl,
        public string $state,
        public int $expiresIn,
    ) {
    }
}
