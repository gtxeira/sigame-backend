<?php

declare(strict_types=1);

namespace Tests\Integration\Notifications\Infrastructure\Persistence;

use App\Notifications\Application\Exception\NotificationAlreadyExistsException;
use App\Notifications\Domain\Notification\Entity\Notification;
use App\Notifications\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Notifications\Domain\Notification\Repository\NotificationSearchCriteria;
use App\Notifications\Domain\Notification\ValueObject\DeduplicationKey;
use App\Notifications\Domain\Notification\ValueObject\NotificationBody;
use App\Notifications\Domain\Notification\ValueObject\NotificationCategory;
use App\Notifications\Domain\Notification\ValueObject\NotificationPayload;
use App\Notifications\Domain\Notification\ValueObject\NotificationReadFilter;
use App\Notifications\Domain\Notification\ValueObject\NotificationTitle;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Domain\ValueObject\Instant;
use Tests\IntegrationTestCase;

function notificationRepository(IntegrationTestCase $test): NotificationRepositoryInterface
{
    /** @var NotificationRepositoryInterface $repository */
    $repository = $test->service(NotificationRepositoryInterface::class);

    return $repository;
}

function notificationFor(
    RecipientId $recipient,
    NotificationType $type,
    string $key,
    NotificationCategory $category = NotificationCategory::GROUP,
): Notification {
    return Notification::create(
        recipientId: $recipient,
        type: $type,
        title: NotificationTitle::fromString('Título'),
        body: NotificationBody::fromString('Corpo'),
        payload: NotificationPayload::fromArray(['category' => $category->value]),
        deduplicationKey: DeduplicationKey::fromString($key),
        createdAt: Instant::now(),
    );
}

it('searches the inbox by read state, category and recipient', function () {
    $recipient = RecipientId::generate();
    $other = RecipientId::generate();
    $repository = notificationRepository($this);

    $first = notificationFor($recipient, NotificationType::MEMBERSHIP_REQUESTED, 'membership-requested:1');
    $second = notificationFor($recipient, NotificationType::LEVEL_REACHED, 'level-reached:3', NotificationCategory::GAMIFICATION);
    $repository->save($first);
    $repository->save($second);
    $repository->save(notificationFor($other, NotificationType::MEMBERSHIP_REQUESTED, 'membership-requested:2'));

    $all = $repository->search(new NotificationSearchCriteria(recipientId: $recipient));
    expect($all->total)->toBe(2);

    $unread = $repository->search(new NotificationSearchCriteria(
        recipientId: $recipient,
        read: NotificationReadFilter::UNREAD,
    ));
    expect($unread->total)->toBe(2);

    $gamification = $repository->search(new NotificationSearchCriteria(
        recipientId: $recipient,
        category: NotificationCategory::GAMIFICATION,
    ));
    expect($gamification->total)->toBe(1)
        ->and($gamification->items[0]->id()->value())->toBe($second->id()->value());

    $first->markRead(Instant::now());
    $repository->save($first);

    expect($repository->countUnread($recipient))->toBe(1);

    $read = $repository->search(new NotificationSearchCriteria(
        recipientId: $recipient,
        read: NotificationReadFilter::READ,
    ));
    expect($read->total)->toBe(1);

    expect($repository->markAllRead($recipient, Instant::now()))->toBe(1)
        ->and($repository->countUnread($recipient))->toBe(0);
});

it('enforces idempotency by recipient and deduplication key', function () {
    $recipient = RecipientId::generate();
    $repository = notificationRepository($this);

    $repository->save(notificationFor($recipient, NotificationType::MEMBERSHIP_REQUESTED, 'same-key'));

    expect(fn () => $repository->save(notificationFor($recipient, NotificationType::MEMBERSHIP_REQUESTED, 'same-key')))
        ->toThrow(NotificationAlreadyExistsException::class);
});
