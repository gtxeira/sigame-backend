<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Domain\Notification\Entity;

use App\Notifications\Domain\Notification\Entity\Notification;
use App\Notifications\Domain\Notification\ValueObject\DeduplicationKey;
use App\Notifications\Domain\Notification\ValueObject\NotificationBody;
use App\Notifications\Domain\Notification\ValueObject\NotificationCategory;
use App\Notifications\Domain\Notification\ValueObject\NotificationPayload;
use App\Notifications\Domain\Notification\ValueObject\NotificationTitle;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Domain\ValueObject\Instant;

it('creates an unread notification with its category derived from the type', function () {
    $notification = Notification::create(
        recipientId: RecipientId::generate(),
        type: NotificationType::MEMBERSHIP_REQUESTED,
        title: NotificationTitle::fromString('Nova solicitação'),
        body: NotificationBody::fromString('Alguém quer entrar no grupo.'),
        payload: NotificationPayload::fromArray(['groupId' => 'group-1']),
        deduplicationKey: DeduplicationKey::fromString('membership-requested:1'),
        createdAt: Instant::now(),
    );

    expect($notification->category())->toBe(NotificationCategory::GROUP)
        ->and($notification->isRead())->toBeFalse()
        ->and($notification->readAt())->toBeNull()
        ->and($notification->payload())->toBe(['groupId' => 'group-1']);
});

it('marks a notification as read only once', function () {
    $notification = Notification::create(
        recipientId: RecipientId::generate(),
        type: NotificationType::LEVEL_REACHED,
        title: NotificationTitle::fromString('Novo nível'),
        body: NotificationBody::fromString('Você alcançou o nível 3.'),
        payload: NotificationPayload::fromArray(['level' => 3]),
        deduplicationKey: DeduplicationKey::fromString('level-reached:3'),
        createdAt: Instant::now(),
    );

    $now = Instant::now();

    expect($notification->markRead($now))->toBeTrue()
        ->and($notification->isRead())->toBeTrue()
        ->and($notification->readAt())->toBe($now)
        ->and($notification->markRead($now))->toBeFalse();
});
