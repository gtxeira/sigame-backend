<?php

declare(strict_types=1);

use App\Gamification\Application\Service\ProgressProvider;
use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;

it('does not save when the progress already exists', function () {
    $userId = PlayerId::generate();
    $progress = UserProgress::create($userId);
    $repository = Mockery::mock(UserProgressRepositoryInterface::class);

    $repository->shouldReceive('findByUserId')
        ->once()
        ->with($userId)
        ->andReturn($progress);
    $repository->shouldNotReceive('save');

    $provider = new ProgressProvider($repository);

    expect($provider->getOrCreate($userId))->toBe($progress);
});

it('saves only when it creates the progress', function () {
    $userId = PlayerId::generate();
    $repository = Mockery::mock(UserProgressRepositoryInterface::class);

    $repository->shouldReceive('findByUserId')
        ->once()
        ->with($userId)
        ->andReturnNull();
    $repository->shouldReceive('save')
        ->once()
        ->with(Mockery::on(
            static fn (UserProgress $progress): bool => $progress->userId()->equals($userId),
        ));

    $provider = new ProgressProvider($repository);
    $progress = $provider->getOrCreate($userId);

    expect($progress->userId()->equals($userId))->toBeTrue()
        ->and($progress->xpBalance())->toBe(0);
});
