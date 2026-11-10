<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\ListXpHistory;

/**
 * Item do histórico de XP.
 */
final readonly class XpHistoryItemView
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $id,
        public string $kind,
        public int $amount,
        public ?string $activityId,
        public ?string $activityDate,
        public ?string $weekKey,
        public string $ruleVersion,
        public ?array $metadata,
        public string $createdAt,
        public ?string $reversedAt,
    ) {
    }
}
