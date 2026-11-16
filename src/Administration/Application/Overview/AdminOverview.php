<?php

declare(strict_types=1);

namespace App\Administration\Application\Overview;

/**
 * Contadores consolidados da plataforma para a visão geral da administração.
 */
final readonly class AdminOverview
{
    public function __construct(
        public int $usersTotal,
        public int $usersActive,
        public int $usersSuspended,
        public int $usersAdmins,
        public int $groupsActive,
        public int $groupsArchived,
        public int $groupsDeleted,
        public int $activitiesScheduled,
        public int $activitiesStarted,
        public int $activitiesFinished,
        public int $activitiesCancelled,
        public int $activitiesInvalidated,
        public int $checkedInLast7Days,
    ) {
    }
}
