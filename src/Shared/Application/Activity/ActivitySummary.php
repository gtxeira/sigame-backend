<?php

declare(strict_types=1);

namespace App\Shared\Application\Activity;

use App\Shared\Domain\ValueObject\Instant;

/**
 * Resumo síncrono de uma atividade para composição de notificações.
 */
final readonly class ActivitySummary
{
    /**
     * @param list<string> $enrolledUserIds
     * @param list<string> $checkedInUserIds
     */
    public function __construct(
        public string $id,
        public string $groupId,
        public string $title,
        public Instant $startsAt,
        public Instant $endsAt,
        public string $meetingPoint,
        public array $enrolledUserIds,
        public array $checkedInUserIds,
    ) {
    }
}
