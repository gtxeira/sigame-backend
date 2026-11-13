<?php

declare(strict_types=1);

use App\Gamification\Application\Service\AchievementUnlocker;
use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\Event\AchievementUnlocked;
use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Achievement\Service\AchievementCatalog;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Instant;

it('registers the legend achievement and publishes the event', function () {
    $userId = PlayerId::generate();
    $achievedAt = Instant::fromString('2026-11-12T10:00:00+00:00')->value();
    $repository = Mockery::mock(UserAchievementRepositoryInterface::class);
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('findByUserIdAndCode')
        ->once()
        ->with($userId, AchievementCode::LENDA)
        ->andReturnNull();
    $saved = null;
    $repository->shouldReceive('insertIfAbsent')
        ->once()
        ->with(Mockery::on(
            static function (UserAchievement $achievement) use (&$saved, $userId, $achievedAt): bool {
                $saved = $achievement;

                return $achievement->userId()->equals($userId)
                    && $achievement->achievedAt()->equals($achievedAt);
            },
        ))
        ->andReturnTrue();
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(
            static fn (AchievementUnlocked $event): bool => $userId->value() === $event->userId()
                && 'LENDA' === $event->code()
                && 'Lenda' === $event->name(),
        ));

    $unlocker = new AchievementUnlocker($repository, new AchievementCatalog(), $dispatcher);
    $unlocker->unlock($userId, 10, $achievedAt);

    expect($saved?->code())->toBe(AchievementCode::LENDA);
});

it('does not register anything below the legend level', function () {
    $repository = Mockery::mock(UserAchievementRepositoryInterface::class);
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);

    $repository->shouldNotReceive('findByUserIdAndCode');
    $repository->shouldNotReceive('insertIfAbsent');
    $dispatcher->shouldNotReceive('dispatch');

    $unlocker = new AchievementUnlocker($repository, new AchievementCatalog(), $dispatcher);
    $unlocker->unlock(PlayerId::generate(), 9);
});

it('does not duplicate an achievement already registered', function () {
    $userId = PlayerId::generate();
    $achievement = UserAchievement::unlock($userId, AchievementCode::LENDA);
    $repository = Mockery::mock(UserAchievementRepositoryInterface::class);
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('findByUserIdAndCode')
        ->once()
        ->with($userId, AchievementCode::LENDA)
        ->andReturn($achievement);
    $repository->shouldNotReceive('insertIfAbsent');
    $dispatcher->shouldNotReceive('dispatch');

    $unlocker = new AchievementUnlocker($repository, new AchievementCatalog(), $dispatcher);
    $unlocker->unlock($userId, 10);
});

it('does not publish the event when another request inserted the achievement first', function () {
    $userId = PlayerId::generate();
    $repository = Mockery::mock(UserAchievementRepositoryInterface::class);
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('findByUserIdAndCode')
        ->once()
        ->with($userId, AchievementCode::LENDA)
        ->andReturnNull();
    $repository->shouldReceive('insertIfAbsent')
        ->once()
        ->andReturnFalse();
    $dispatcher->shouldNotReceive('dispatch');

    $unlocker = new AchievementUnlocker($repository, new AchievementCatalog(), $dispatcher);
    $unlocker->unlock($userId, 10);
});
