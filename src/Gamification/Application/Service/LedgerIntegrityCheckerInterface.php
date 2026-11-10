<?php

declare(strict_types=1);

namespace App\Gamification\Application\Service;

use App\Gamification\Application\ReadModel\LedgerIntegrityIssue;

/**
 * Porta de leitura dos problemas de integridade referencial do ledger.
 */
interface LedgerIntegrityCheckerInterface
{
    /**
     * @return list<LedgerIntegrityIssue>
     */
    public function issues(?string $userId = null): array;
}
