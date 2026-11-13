<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;

/**
 * Monta o plano de uma notificação a partir da intenção publicada.
 */
final readonly class NotificationPlanner
{
    public function __construct(
        private NotificationAudienceResolver $audience,
        private NotificationContentFactory $contentFactory,
        private NotificationChannelPolicy $channelPolicy,
    ) {
    }

    public function plan(NotificationIntent $intent): ?NotificationPlan
    {
        $content = $this->contentFactory->create($intent->type, $intent->context);

        if (null === $content) {
            return null;
        }

        $recipients = $this->recipients($intent);

        if ([] === $recipients) {
            return null;
        }

        $exclude = null === $intent->actorId ? [] : [$intent->actorId];

        return new NotificationPlan(
            type: $intent->type,
            deduplicationKey: $intent->deduplicationKey,
            content: $content,
            recipientIds: $recipients,
            excludeUserIds: $exclude,
            includeInactive: $intent->includeInactive,
            channels: $this->channelPolicy->channelsFor($intent->type),
            recipientChannels: $this->recipientChannels($intent, $recipients),
        );
    }

    /**
     * @param list<string> $recipients
     *
     * @return array<string, list<NotificationChannel>>
     */
    private function recipientChannels(NotificationIntent $intent, array $recipients): array
    {
        $channels = [];

        foreach ($recipients as $recipientId) {
            $channels[$recipientId] = $this->channelPolicy->channelsForRecipient(
                $intent->type,
                $intent->context,
                $recipientId,
            );
        }

        return $channels;
    }

    /**
     * @return list<string>
     */
    private function recipients(NotificationIntent $intent): array
    {
        $context = $intent->context;

        return match ($intent->type) {
            NotificationType::MEMBERSHIP_REQUESTED => $this->groupAdmins($context),
            NotificationType::MEMBERSHIP_APPROVED,
            NotificationType::MEMBERSHIP_REJECTED,
            NotificationType::MEMBER_REMOVED,
            NotificationType::MEMBERSHIP_PROMOTED,
            NotificationType::MEMBERSHIP_DEMOTED,
            NotificationType::SUPERVISOR_DESIGNATED => $this->single($context, 'userId'),
            NotificationType::SUPERVISOR_REMOVED => $this->single($context, 'supervisorId'),
            NotificationType::GROUP_ACTIVATED => $this->groupMembers($context),
            NotificationType::GROUP_ARCHIVED => $this->groupMembersExceptOwner($context),
            NotificationType::GROUP_ARCHIVED_OWNER => $this->single($context, 'ownerId'),
            NotificationType::GROUP_OWNER_CHANGED => $this->ownershipChange($context),
            NotificationType::GROUP_DELETED => $this->groupMembersAndOwner($context),
            NotificationType::ACTIVITY_CREATED => $this->groupMembers($context),
            NotificationType::ACTIVITY_UPDATED,
            NotificationType::ACTIVITY_RESCHEDULED,
            NotificationType::ACTIVITY_CANCELLED,
            NotificationType::ACTIVITY_INVALIDATED => $this->activityEnrolled($context),
            NotificationType::ACTIVITY_FINISHED => $this->groupAdminsAndSupervisor($context),
            NotificationType::ATTENDANCE_VALIDATED => $this->single($context, 'userId'),
            NotificationType::LEVEL_REACHED,
            NotificationType::LEVEL_LOST,
            NotificationType::WEEKLY_GOAL_COMPLETED,
            NotificationType::ACHIEVEMENT_UNLOCKED,
            NotificationType::ACCOUNT_SUSPENDED,
            NotificationType::ACCOUNT_REACTIVATED => $this->single($context, 'userId'),
        };
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     *
     * @return list<string>
     */
    private function groupAdmins(array $context): array
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        return null === $group ? [] : $this->audience->groupAdmins($group);
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     *
     * @return list<string>
     */
    private function groupMembers(array $context): array
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        return null === $group ? [] : $this->audience->approvedGroupMembers($group);
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     *
     * @return list<string>
     */
    private function groupMembersExceptOwner(array $context): array
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return [];
        }

        return array_values(array_diff(
            $this->audience->approvedGroupMembers($group),
            array_filter([$group->ownerId]),
        ));
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     *
     * @return list<string>
     */
    private function groupMembersAndOwner(array $context): array
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return [];
        }

        return array_values(array_unique([
            ...$this->audience->approvedGroupMembers($group),
            ...array_filter([$group->ownerId]),
        ]));
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     *
     * @return list<string>
     */
    private function ownershipChange(array $context): array
    {
        return array_values(array_unique(array_filter([
            $this->string($context, 'previousOwnerId'),
            $this->string($context, 'ownerId'),
        ])));
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     *
     * @return list<string>
     */
    private function groupAdminsAndSupervisor(array $context): array
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        return null === $group ? [] : $this->audience->groupAdminsAndSupervisor($group);
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     *
     * @return list<string>
     */
    private function activityEnrolled(array $context): array
    {
        $activity = $this->audience->activity($this->string($context, 'activityId'));

        return null === $activity ? [] : $this->audience->enrolledParticipants($activity);
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     *
     * @return list<string>
     */
    private function single(array $context, string $key): array
    {
        $value = $this->string($context, $key);

        return '' === $value ? [] : [$value];
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function string(array $context, string $key): string
    {
        $value = $context[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
