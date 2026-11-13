<?php

declare(strict_types=1);

use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Domain\ValueObject\Instant;

it('unlocks an achievement without a celebration', function () {
    $achievement = UserAchievement::unlock(PlayerId::generate(), AchievementCode::LENDA);

    expect($achievement->code())->toBe(AchievementCode::LENDA)
        ->and($achievement->celebratedAt())->toBeNull()
        ->and($achievement->isCelebrated())->toBeFalse();
});

it('celebrates an achievement only once', function () {
    $achievement = UserAchievement::unlock(PlayerId::generate(), AchievementCode::LENDA);
    $first = Instant::fromString('2026-11-12T10:00:00+00:00')->value();
    $second = Instant::fromString('2026-11-13T10:00:00+00:00')->value();

    expect($achievement->celebrate($first))->toBeTrue()
        ->and($achievement->celebratedAt())->toBe($first)
        ->and($achievement->celebrate($second))->toBeFalse()
        ->and($achievement->celebratedAt())->toBe($first);
});
