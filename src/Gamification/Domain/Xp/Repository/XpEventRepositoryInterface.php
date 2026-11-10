<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Xp\Repository;

use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Xp\Entity\XpEvent;
use App\Gamification\Domain\Xp\ValueObject\XpKind;

interface XpEventRepositoryInterface
{
    public function save(XpEvent $event): void;

    public function findActiveByIdempotencyKey(string $key): ?XpEvent;

    public function findActiveWeeklyEvent(PlayerId $userId, string $weekKey, XpKind $kind): ?XpEvent;

    /**
     * @return list<XpEvent>
     */
    public function findActiveByActivityAndUser(string $activityId, PlayerId $userId): array;

    public function countActiveActivityEventsOnDate(PlayerId $userId, string $activityDate): int;

    /**
     * @return list<string>
     */
    public function findActiveActivityDates(PlayerId $userId): array;

    /**
     * @return list<XpEvent>
     */
    public function findActiveActivityEvents(PlayerId $userId): array;

    /**
     * @return list<XpEvent>
     */
    public function findHistory(PlayerId $userId, int $page, int $perPage): array;

    public function countHistory(PlayerId $userId): int;
}
