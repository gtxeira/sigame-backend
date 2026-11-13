<?php

declare(strict_types=1);

use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserDeleted;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;

it('removes the achievements when the account is deleted', function () {
    /** @var UserRepositoryInterface $userRepository */
    $userRepository = $this->service(UserRepositoryInterface::class);
    /** @var UserAchievementRepositoryInterface $achievementRepository */
    $achievementRepository = $this->service(UserAchievementRepositoryInterface::class);
    /** @var EventDispatcherInterface $eventDispatcher */
    $eventDispatcher = $this->service(EventDispatcherInterface::class);

    $user = User::register(
        cpf: null,
        email: Email::fromString('achievement-deleted@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Achievement Deleted User',
    );
    $userRepository->save($user);

    $userId = PlayerId::fromString($user->id()->value());
    $achievementRepository->save(UserAchievement::unlock($userId, AchievementCode::LENDA));

    $eventDispatcher->dispatch(UserDeleted::now($userId->value()));

    expect($achievementRepository->findByUserId($userId))->toBe([]);
});
