<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Application\Service;

use App\Notifications\Application\Service\NotificationChannelPolicy;
use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;

it('sends only account events by email in the first version', function () {
    $policy = new NotificationChannelPolicy();

    expect($policy->channelsFor(NotificationType::ACCOUNT_SUSPENDED))->toBe([NotificationChannel::EMAIL])
        ->and($policy->channelsFor(NotificationType::ACCOUNT_REACTIVATED))->toBe([NotificationChannel::EMAIL])
        ->and($policy->channelsFor(NotificationType::MEMBERSHIP_REQUESTED))->toBe([])
        ->and($policy->channelsFor(NotificationType::ACTIVITY_CANCELLED))->toBe([]);
});
