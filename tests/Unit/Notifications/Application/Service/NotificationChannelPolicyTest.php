<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Application\Service;

use App\Notifications\Application\Service\NotificationChannelPolicy;
use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;

it('sends account and group owner events by email', function () {
    $policy = new NotificationChannelPolicy();

    expect($policy->channelsFor(NotificationType::ACCOUNT_SUSPENDED))->toBe([NotificationChannel::EMAIL])
        ->and($policy->channelsFor(NotificationType::ACCOUNT_REACTIVATED))->toBe([NotificationChannel::EMAIL])
        ->and($policy->channelsFor(NotificationType::GROUP_ARCHIVED_OWNER))->toBe([NotificationChannel::EMAIL])
        ->and($policy->channelsFor(NotificationType::GROUP_ARCHIVED))->toBe([])
        ->and($policy->channelsFor(NotificationType::GROUP_DELETED))->toBe([])
        ->and($policy->channelsFor(NotificationType::MEMBERSHIP_REQUESTED))->toBe([])
        ->and($policy->channelsFor(NotificationType::ACTIVITY_CANCELLED))->toBe([]);
});
