<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\ListXpHistory;

use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Xp\Entity\XpEvent;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;

/**
 * Lista o ledger de XP do usuário, do mais recente para o mais antigo.
 */
final readonly class ListXpHistoryHandler
{
    public function __construct(
        private XpEventRepositoryInterface $xpEventRepository,
    ) {
    }

    public function __invoke(PlayerId $userId, int $page, int $perPage): XpHistoryView
    {
        $items = $this->xpEventRepository->findHistory($userId, $page, $perPage);

        return new XpHistoryView(
            items: array_map(
                static fn (XpEvent $event): XpHistoryItemView => new XpHistoryItemView(
                    id: $event->id()->value(),
                    kind: $event->kind()->value,
                    amount: $event->amount(),
                    activityId: $event->activityId(),
                    activityDate: $event->activityDate(),
                    weekKey: $event->weekKey(),
                    ruleVersion: $event->ruleVersion(),
                    metadata: $event->metadata(),
                    createdAt: $event->createdAt()->toIsoStringWithMilliseconds(),
                    reversedAt: $event->reversedAt()?->toIsoStringWithMilliseconds(),
                ),
                $items,
            ),
            total: $this->xpEventRepository->countHistory($userId),
        );
    }
}
