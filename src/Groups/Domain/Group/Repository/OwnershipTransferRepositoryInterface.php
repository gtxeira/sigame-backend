<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Repository;

use App\Groups\Domain\Group\Entity\OwnershipTransfer;

/**
 * Porta append-only do histórico de responsabilidade dos grupos.
 */
interface OwnershipTransferRepositoryInterface
{
    public function record(OwnershipTransfer $transfer): void;
}
