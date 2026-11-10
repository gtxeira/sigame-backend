<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Application\Service;

use App\Notifications\Application\Service\NotificationAudienceResolver;
use App\Notifications\Application\Service\NotificationChannelPolicy;
use App\Notifications\Application\Service\NotificationContentFactory;
use App\Notifications\Application\Service\NotificationIntent;
use App\Notifications\Application\Service\NotificationPlanner;
use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use App\Shared\Application\Activity\ActivitySummary;
use App\Shared\Application\Activity\ActivitySummaryReaderInterface;
use App\Shared\Application\Group\GroupSummary;
use App\Shared\Application\Group\GroupSummaryReaderInterface;
use App\Shared\Application\User\UserContact;
use App\Shared\Application\User\UserContactReaderInterface;
use App\Shared\Domain\ValueObject\Instant;
use Mockery as m;

function notificationPlanner(
    GroupSummaryReaderInterface $groups,
    ActivitySummaryReaderInterface $activities,
    UserContactReaderInterface $contacts,
): NotificationPlanner {
    $audience = new NotificationAudienceResolver($groups, $activities, $contacts);

    return new NotificationPlanner(
        $audience,
        new NotificationContentFactory($audience),
        new NotificationChannelPolicy(),
    );
}

it('plans a membership request for every group admin and excludes the requester', function () {
    $groups = m::mock(GroupSummaryReaderInterface::class);
    $activities = m::mock(ActivitySummaryReaderInterface::class);
    $contacts = m::mock(UserContactReaderInterface::class);

    $groups->shouldReceive('summary')->with('group-1')->andReturn(new GroupSummary(
        id: 'group-1',
        name: 'Corrida Centro',
        adminIds: ['admin-1', 'admin-2'],
        supervisorId: null,
        approvedMemberIds: ['admin-1', 'admin-2'],
    ));
    $contacts->shouldReceive('contact')->with('requester-1')->andReturn(new UserContact(
        id: 'requester-1',
        name: 'Ana',
        email: 'ana@example.com',
        active: true,
    ));

    $plan = notificationPlanner($groups, $activities, $contacts)->plan(new NotificationIntent(
        type: NotificationType::MEMBERSHIP_REQUESTED,
        deduplicationKey: 'membership-requested:1',
        context: [
            'groupId' => 'group-1',
            'membershipId' => 'membership-1',
            'requesterId' => 'requester-1',
        ],
        actorId: 'requester-1',
    ));

    expect($plan)->not->toBeNull()
        ->and($plan->recipientIds)->toBe(['admin-1', 'admin-2'])
        ->and($plan->excludeUserIds)->toBe(['requester-1'])
        ->and($plan->content->title)->toBe('Nova solicitação de entrada')
        ->and($plan->content->body)->toContain('Ana')
        ->and($plan->channels)->toBe([]);
});

it('plans an activity creation for approved members and excludes the creator', function () {
    $groups = m::mock(GroupSummaryReaderInterface::class);
    $activities = m::mock(ActivitySummaryReaderInterface::class);
    $contacts = m::mock(UserContactReaderInterface::class);

    $groups->shouldReceive('summary')->with('group-1')->andReturn(new GroupSummary(
        id: 'group-1',
        name: 'Corrida Centro',
        adminIds: ['creator-1'],
        supervisorId: null,
        approvedMemberIds: ['creator-1', 'member-1'],
    ));
    $activities->shouldReceive('summary')->with('activity-1')->andReturn(new ActivitySummary(
        id: 'activity-1',
        groupId: 'group-1',
        title: 'Treino de terça',
        startsAt: Instant::now(),
        endsAt: Instant::now(),
        meetingPoint: 'Praça Central',
        enrolledUserIds: [],
        checkedInUserIds: [],
    ));

    $plan = notificationPlanner($groups, $activities, $contacts)->plan(new NotificationIntent(
        type: NotificationType::ACTIVITY_CREATED,
        deduplicationKey: 'activity-created:1',
        context: [
            'activityId' => 'activity-1',
            'groupId' => 'group-1',
            'createdBy' => 'creator-1',
        ],
        actorId: 'creator-1',
    ));

    expect($plan)->not->toBeNull()
        ->and($plan->recipientIds)->toBe(['creator-1', 'member-1'])
        ->and($plan->excludeUserIds)->toBe(['creator-1'])
        ->and($plan->content->body)->toContain('Treino de terça');
});

it('plans account suspension for the holder, including inactive users and email', function () {
    $groups = m::mock(GroupSummaryReaderInterface::class);
    $activities = m::mock(ActivitySummaryReaderInterface::class);
    $contacts = m::mock(UserContactReaderInterface::class);

    $plan = notificationPlanner($groups, $activities, $contacts)->plan(new NotificationIntent(
        type: NotificationType::ACCOUNT_SUSPENDED,
        deduplicationKey: 'account-suspended:1',
        context: [
            'userId' => 'user-1',
            'suspensionId' => 'suspension-1',
            'reason' => 'Fraude confirmada.',
        ],
        actorId: 'admin-1',
        includeInactive: true,
    ));

    expect($plan)->not->toBeNull()
        ->and($plan->recipientIds)->toBe(['user-1'])
        ->and($plan->includeInactive)->toBeTrue()
        ->and($plan->channels)->toBe([NotificationChannel::EMAIL])
        ->and($plan->content->body)->toContain('Fraude confirmada.');
});

it('plans activity completion for admins and the supervisor', function () {
    $groups = m::mock(GroupSummaryReaderInterface::class);
    $activities = m::mock(ActivitySummaryReaderInterface::class);
    $contacts = m::mock(UserContactReaderInterface::class);

    $groups->shouldReceive('summary')->with('group-1')->andReturn(new GroupSummary(
        id: 'group-1',
        name: 'Corrida Centro',
        adminIds: ['admin-1'],
        supervisorId: 'supervisor-1',
        approvedMemberIds: ['admin-1', 'supervisor-1'],
    ));
    $activities->shouldReceive('summary')->with('activity-1')->andReturn(new ActivitySummary(
        id: 'activity-1',
        groupId: 'group-1',
        title: 'Treino de terça',
        startsAt: Instant::now(),
        endsAt: Instant::now(),
        meetingPoint: 'Praça Central',
        enrolledUserIds: ['member-1'],
        checkedInUserIds: ['member-1'],
    ));

    $plan = notificationPlanner($groups, $activities, $contacts)->plan(new NotificationIntent(
        type: NotificationType::ACTIVITY_FINISHED,
        deduplicationKey: 'activity-finished:1',
        context: [
            'activityId' => 'activity-1',
            'groupId' => 'group-1',
            'automatic' => true,
        ],
        actorId: 'admin-1',
    ));

    expect($plan)->not->toBeNull()
        ->and($plan->recipientIds)->toBe(['admin-1', 'supervisor-1'])
        ->and($plan->excludeUserIds)->toBe(['admin-1'])
        ->and($plan->content->body)->toContain('automática');
});

it('returns no plan when the referenced group is missing', function () {
    $groups = m::mock(GroupSummaryReaderInterface::class);
    $activities = m::mock(ActivitySummaryReaderInterface::class);
    $contacts = m::mock(UserContactReaderInterface::class);

    $groups->shouldReceive('summary')->with('missing')->andReturnNull();

    $plan = notificationPlanner($groups, $activities, $contacts)->plan(new NotificationIntent(
        type: NotificationType::GROUP_ACTIVATED,
        deduplicationKey: 'group-activated:missing',
        context: ['groupId' => 'missing'],
    ));

    expect($plan)->toBeNull();
});
