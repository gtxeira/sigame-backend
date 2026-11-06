<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Application\DTO\SocialFlow;

/**
 * Persistência efêmera do fluxo OAuth (state, PKCE e redirect URI).
 */
interface SocialFlowStoreInterface
{
    public function save(SocialFlow $flow, int $ttlSeconds): void;

    /**
     * Lê e remove o fluxo. Retorna null se não existir ou já tiver sido usado.
     */
    public function consume(string $flowId): ?SocialFlow;

    public function delete(string $flowId): void;
}
