<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\ListXpHistory;

/**
 * Página do histórico de XP.
 */
final readonly class XpHistoryView
{
    /**
     * @param list<XpHistoryItemView> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
