<?php

declare(strict_types=1);

namespace App\Administration\Presentation\Http\Response;

use App\Administration\Application\Overview\AdminOverview;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Contadores consolidados da plataforma para a administração.')]
final readonly class AdminOverviewResponse
{
    /**
     * @param array{total: int, active: int, suspended: int, admins: int}                          $users
     * @param array{active: int, archived: int, deleted: int}                                      $groups
     * @param array{scheduled: int, started: int, finished: int, cancelled: int, invalidated: int} $activities
     * @param array{checkedInLast7Days: int}                                                       $attendances
     */
    public function __construct(
        #[OA\Property(
            type: 'object',
            required: ['total', 'active', 'suspended', 'admins'],
            properties: [
                new OA\Property(property: 'total', type: 'integer', example: 120),
                new OA\Property(property: 'active', type: 'integer', example: 118),
                new OA\Property(property: 'suspended', type: 'integer', example: 2),
                new OA\Property(property: 'admins', type: 'integer', example: 1),
            ],
        )]
        public array $users,

        #[OA\Property(
            type: 'object',
            required: ['active', 'archived', 'deleted'],
            properties: [
                new OA\Property(property: 'active', type: 'integer', example: 30),
                new OA\Property(property: 'archived', type: 'integer', example: 4),
                new OA\Property(property: 'deleted', type: 'integer', example: 1),
            ],
        )]
        public array $groups,

        #[OA\Property(
            type: 'object',
            required: ['scheduled', 'started', 'finished', 'cancelled', 'invalidated'],
            properties: [
                new OA\Property(property: 'scheduled', type: 'integer', example: 12),
                new OA\Property(property: 'started', type: 'integer', example: 2),
                new OA\Property(property: 'finished', type: 'integer', example: 80),
                new OA\Property(property: 'cancelled', type: 'integer', example: 5),
                new OA\Property(property: 'invalidated', type: 'integer', example: 1),
            ],
        )]
        public array $activities,

        #[OA\Property(
            type: 'object',
            required: ['checkedInLast7Days'],
            properties: [
                new OA\Property(property: 'checkedInLast7Days', type: 'integer', example: 210),
            ],
        )]
        public array $attendances,
    ) {
    }

    public static function fromOverview(AdminOverview $overview): self
    {
        return new self(
            users: [
                'total' => $overview->usersTotal,
                'active' => $overview->usersActive,
                'suspended' => $overview->usersSuspended,
                'admins' => $overview->usersAdmins,
            ],
            groups: [
                'active' => $overview->groupsActive,
                'archived' => $overview->groupsArchived,
                'deleted' => $overview->groupsDeleted,
            ],
            activities: [
                'scheduled' => $overview->activitiesScheduled,
                'started' => $overview->activitiesStarted,
                'finished' => $overview->activitiesFinished,
                'cancelled' => $overview->activitiesCancelled,
                'invalidated' => $overview->activitiesInvalidated,
            ],
            attendances: [
                'checkedInLast7Days' => $overview->checkedInLast7Days,
            ],
        );
    }
}
