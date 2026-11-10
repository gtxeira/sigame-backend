<?php

declare(strict_types=1);

namespace App\Gamification\Application\ReadModel;

/**
 * Totais do ledger de XP de um usuário.
 *
 * `balance` soma todos os lançamentos, inclusive o original revertido e o
 * reversal negativo. `lifetime` exclui apenas o decay.
 */
final readonly class XpLedgerTotals
{
    public function __construct(
        public string $userId,
        public int $balance,
        public int $lifetime,
    ) {
    }
}
