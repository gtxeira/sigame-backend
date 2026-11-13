<?php

declare(strict_types=1);

use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\Event\LevelReached;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Notifications\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Notifications\Domain\Notification\ValueObject\DeduplicationKey;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;

it('unlocks the legend achievement and notifies when the level is reached', function () {
    /** @var UserRepositoryInterface $userRepository */
    $userRepository = $this->service(UserRepositoryInterface::class);
    /** @var UserAchievementRepositoryInterface $achievementRepository */
    $achievementRepository = $this->service(UserAchievementRepositoryInterface::class);
    /** @var NotificationRepositoryInterface $notificationRepository */
    $notificationRepository = $this->service(NotificationRepositoryInterface::class);
    /** @var EventDispatcherInterface $eventDispatcher */
    $eventDispatcher = $this->service(EventDispatcherInterface::class);

    $user = User::register(
        cpf: null,
        email: Email::fromString('achievement-level@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Achievement Level User',
    );
    $userRepository->save($user);

    $userId = PlayerId::fromString($user->id()->value());

    $eventDispatcher->dispatch(LevelReached::now($userId->value(), 9, 10));

    $achievement = $achievementRepository->findByUserIdAndCode($userId, AchievementCode::LENDA);

    expect($achievement)->not->toBeNull()
        ->and($achievement?->code())->toBe(AchievementCode::LENDA)
        ->and($achievement?->celebratedAt())->toBeNull();

    $notification = $notificationRepository->findByDeduplicationKey(
        RecipientId::fromString($userId->value()),
        DeduplicationKey::fromString('achievement-unlocked:'.$userId->value().':LENDA'),
    );

    expect($notification)->not->toBeNull()
        ->and($notification?->type())->toBe(NotificationType::ACHIEVEMENT_UNLOCKED)
        ->and($notification?->payload()['code'])->toBe('LENDA');
});
