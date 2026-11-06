<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Application\DTO\LinkTicket;

/**
 * Persistência efêmera dos tickets de vínculo entregues no 409.
 */
interface SocialLinkTicketStoreInterface
{
    public function save(string $token, LinkTicket $ticket, int $ttlSeconds): void;

    /**
     * Lê e remove o ticket. Retorna null se não existir ou já tiver sido usado.
     */
    public function consume(string $token): ?LinkTicket;
}
