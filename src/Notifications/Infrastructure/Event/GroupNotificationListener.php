<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Event;

use App\Groups\Domain\Group\Event\GroupActivated;
use App\Groups\Domain\Group\Event\GroupArchived;
use App\Groups\Domain\Group\Event\GroupDeleted;
use App\Groups\Domain\Group\Event\MemberRemoved;
use App\Groups\Domain\Group\Event\MembershipApproved;
use App\Groups\Domain\Group\Event\MembershipDemoted;
use App\Groups\Domain\Group\Event\MembershipPromoted;
use App\Groups\Domain\Group\Event\MembershipRejected;
use App\Groups\Domain\Group\Event\MembershipRequested;
use App\Groups\Domain\Group\Event\SupervisorDesignated;
use App\Groups\Domain\Group\Event\SupervisorRemoved;
use App\Notifications\Application\Service\NotificationIntent;
use App\Notifications\Application\Service\NotificationRequestPublisher;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Traduz eventos de grupos em intenções de notificação.
 */
final readonly class GroupNotificationListener
{
    public function __construct(
        private NotificationRequestPublisher $publisher,
    ) {
    }

    #[AsEventListener(event: 'group.membership.requested')]
    public function onMembershipRequested(MembershipRequested $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::MEMBERSHIP_REQUESTED,
            deduplicationKey: 'membership-requested:'.$event->membershipId(),
            context: [
                'groupId' => $event->groupId(),
                'membershipId' => $event->membershipId(),
                'requesterId' => $event->userId(),
            ],
            actorId: $event->userId(),
        ));
    }

    #[AsEventListener(event: 'group.membership.approved')]
    public function onMembershipApproved(MembershipApproved $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::MEMBERSHIP_APPROVED,
            deduplicationKey: 'membership-approved:'.$event->membershipId(),
            context: [
                'groupId' => $event->groupId(),
                'membershipId' => $event->membershipId(),
                'userId' => $event->userId(),
                'decidedBy' => $event->decidedBy(),
            ],
            actorId: $event->decidedBy(),
        ));
    }

    #[AsEventListener(event: 'group.membership.rejected')]
    public function onMembershipRejected(MembershipRejected $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::MEMBERSHIP_REJECTED,
            deduplicationKey: 'membership-rejected:'.$event->membershipId(),
            context: [
                'groupId' => $event->groupId(),
                'membershipId' => $event->membershipId(),
                'userId' => $event->userId(),
                'decidedBy' => $event->decidedBy(),
            ],
            actorId: $event->decidedBy(),
        ));
    }

    #[AsEventListener(event: 'group.member.removed')]
    public function onMemberRemoved(MemberRemoved $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::MEMBER_REMOVED,
            deduplicationKey: 'member-removed:'.$event->membershipId(),
            context: [
                'groupId' => $event->groupId(),
                'membershipId' => $event->membershipId(),
                'userId' => $event->userId(),
                'removedBy' => $event->removedBy(),
                'reason' => $event->reason(),
            ],
            actorId: $event->removedBy(),
        ));
    }

    #[AsEventListener(event: 'group.membership.promoted')]
    public function onMembershipPromoted(MembershipPromoted $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::MEMBERSHIP_PROMOTED,
            deduplicationKey: 'membership-promoted:'.$event->membershipId().':'.$event->occurredAt()->toIsoStringWithMicroseconds(),
            context: [
                'groupId' => $event->groupId(),
                'membershipId' => $event->membershipId(),
                'userId' => $event->userId(),
                'actorId' => $event->actorId(),
            ],
            actorId: $event->actorId(),
        ));
    }

    #[AsEventListener(event: 'group.membership.demoted')]
    public function onMembershipDemoted(MembershipDemoted $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::MEMBERSHIP_DEMOTED,
            deduplicationKey: 'membership-demoted:'.$event->membershipId().':'.$event->occurredAt()->toIsoStringWithMicroseconds(),
            context: [
                'groupId' => $event->groupId(),
                'membershipId' => $event->membershipId(),
                'userId' => $event->userId(),
                'actorId' => $event->actorId(),
            ],
            actorId: $event->actorId(),
        ));
    }

    #[AsEventListener(event: 'group.supervisor.designated')]
    public function onSupervisorDesignated(SupervisorDesignated $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::SUPERVISOR_DESIGNATED,
            deduplicationKey: 'supervisor-designated:'.$event->membershipId(),
            context: [
                'groupId' => $event->groupId(),
                'membershipId' => $event->membershipId(),
                'userId' => $event->userId(),
                'actorId' => $event->actorId(),
            ],
            actorId: $event->actorId(),
        ));
    }

    #[AsEventListener(event: 'group.supervisor.removed')]
    public function onSupervisorRemoved(SupervisorRemoved $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::SUPERVISOR_REMOVED,
            deduplicationKey: 'supervisor-removed:'.$event->groupId().':'.$event->occurredAt()->toIsoStringWithMicroseconds(),
            context: [
                'groupId' => $event->groupId(),
                'supervisorId' => $event->supervisorId(),
                'actorId' => $event->actorId(),
            ],
            actorId: $event->actorId(),
        ));
    }

    #[AsEventListener(event: 'group.activated')]
    public function onGroupActivated(GroupActivated $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::GROUP_ACTIVATED,
            deduplicationKey: 'group-activated:'.$event->groupId(),
            context: ['groupId' => $event->groupId()],
        ));
    }

    #[AsEventListener(event: 'group.archived')]
    public function onGroupArchived(GroupArchived $event): void
    {
        $occurredAt = $event->occurredAt()->toIsoStringWithMicroseconds();

        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::GROUP_ARCHIVED,
            deduplicationKey: 'group-archived:'.$event->groupId().':'.$occurredAt,
            context: [
                'groupId' => $event->groupId(),
                'reason' => $event->reason(),
                'automatic' => $event->automatic(),
            ],
            actorId: $event->archivedBy(),
        ));

        // RN15.14: o ator não recebe notificação da própria ação.
        if (!$event->automatic() && $event->archivedBy() === $event->createdBy()) {
            return;
        }

        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::GROUP_ARCHIVED_OWNER,
            deduplicationKey: 'group-archived-owner:'.$event->groupId().':'.$occurredAt,
            context: [
                'groupId' => $event->groupId(),
                'ownerId' => $event->createdBy(),
                'reason' => $event->reason(),
                'automatic' => $event->automatic(),
            ],
            actorId: $event->automatic() ? null : $event->archivedBy(),
        ));
    }

    #[AsEventListener(event: 'group.deleted')]
    public function onGroupDeleted(GroupDeleted $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::GROUP_DELETED,
            deduplicationKey: 'group-deleted:'.$event->groupId(),
            context: [
                'groupId' => $event->groupId(),
                'reason' => $event->reason(),
            ],
        ));
    }
}
