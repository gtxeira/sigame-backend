<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Shared\Application\Activity\ActivitySummary;
use App\Shared\Application\Activity\ActivitySummaryReaderInterface;
use App\Shared\Application\Group\GroupSummary;
use App\Shared\Application\Group\GroupSummaryReaderInterface;
use App\Shared\Application\User\UserContactReaderInterface;

/**
 * Resolve o público de cada tipo de notificação.
 */
final readonly class NotificationAudienceResolver
{
    public function __construct(
        private GroupSummaryReaderInterface $groupSummaryReader,
        private ActivitySummaryReaderInterface $activitySummaryReader,
        private UserContactReaderInterface $userContactReader,
    ) {
    }

    public function group(string $groupId): ?GroupSummary
    {
        return $this->groupSummaryReader->summary($groupId);
    }

    public function activity(string $activityId): ?ActivitySummary
    {
        return $this->activitySummaryReader->summary($activityId);
    }

    /**
     * @return list<string>
     */
    public function groupAdmins(GroupSummary $group): array
    {
        return $group->adminIds;
    }

    /**
     * @return list<string>
     */
    public function groupAdminsAndSupervisor(GroupSummary $group): array
    {
        $ids = $group->adminIds;

        if (null !== $group->supervisorId) {
            $ids[] = $group->supervisorId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    public function approvedGroupMembers(GroupSummary $group): array
    {
        return $group->approvedMemberIds;
    }

    /**
     * @return list<string>
     */
    public function enrolledParticipants(ActivitySummary $activity): array
    {
        return $activity->enrolledUserIds;
    }

    public function userName(string $userId): ?string
    {
        return $this->userContactReader->contact($userId)?->name;
    }
}
