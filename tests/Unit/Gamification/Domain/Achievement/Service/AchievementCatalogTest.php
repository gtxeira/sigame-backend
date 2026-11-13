<?php

declare(strict_types=1);

use App\Gamification\Domain\Achievement\Service\AchievementCatalog;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;

it('unlocks only the achievements reached by the level', function (int $level, array $expected) {
    $catalog = new AchievementCatalog();

    expect($catalog->reachedAt($level))->toBe($expected);
})->with([
    'below the legend level' => [9, []],
    'at the legend level' => [10, [AchievementCode::LENDA]],
    'above the legend level' => [11, [AchievementCode::LENDA]],
]);

it('lists the whole achievement catalog', function () {
    expect((new AchievementCatalog())->all())->toBe([AchievementCode::LENDA])
        ->and(AchievementCode::LENDA->label())->toBe('Lenda')
        ->and(AchievementCode::LENDA->description())->toBe('Alcance o nível 10.');
});
