<?php

declare(strict_types=1);

namespace App\Gamification\Application\Service;

use App\Gamification\Application\ReadModel\XpLedgerTotals;

/**
 * Porta de leitura dos totais agregados do ledger de XP.
 */
interface LedgerTotalsReaderInterface
{
    /**
     * @return list<XpLedgerTotals>
     */
    public function totals(?string $userId = null): array;
}
