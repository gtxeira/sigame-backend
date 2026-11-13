<?php

declare(strict_types=1);

use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;

it('stores, lists and removes achievements by user', function () {
    /** @var UserRepositoryInterface $userRepository */
    $userRepository = $this->service(UserRepositoryInterface::class);
    /** @var UserAchievementRepositoryInterface $achievementRepository */
    $achievementRepository = $this->service(UserAchievementRepositoryInterface::class);

    $user = User::register(
        cpf: null,
        email: Email::fromString('achievement-repository@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Achievement User',
    );
    $userRepository->save($user);

    $userId = PlayerId::fromString($user->id()->value());
    $achievedAt = Instant::fromString('2026-11-12T10:00:00+00:00')->value();

    expect($achievementRepository->findByUserId($userId))->toBe([]);

    $achievementRepository->save(UserAchievement::unlock($userId, AchievementCode::LENDA, $achievedAt));

    $found = $achievementRepository->findByUserIdAndCode($userId, AchievementCode::LENDA);

    expect($found)->not->toBeNull()
        ->and($found?->achievedAt()->equals($achievedAt))->toBeTrue()
        ->and($achievementRepository->findByUserId($userId))->toHaveCount(1);

    $achievementRepository->deleteByUserId($userId);

    expect($achievementRepository->findByUserId($userId))->toBe([]);
});

it('inserts an achievement only once when concurrent requests race', function () {
    /** @var UserRepositoryInterface $userRepository */
    $userRepository = $this->service(UserRepositoryInterface::class);
    /** @var UserAchievementRepositoryInterface $achievementRepository */
    $achievementRepository = $this->service(UserAchievementRepositoryInterface::class);

    $user = User::register(
        cpf: null,
        email: Email::fromString('achievement-race@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Achievement Race',
    );
    $userRepository->save($user);

    $userId = PlayerId::fromString($user->id()->value());
    $achievement = UserAchievement::unlock($userId, AchievementCode::LENDA);

    expect($achievementRepository->insertIfAbsent($achievement))->toBeTrue()
        ->and($achievementRepository->insertIfAbsent($achievement))->toBeFalse()
        ->and($achievementRepository->findByUserId($userId))->toHaveCount(1);
});
