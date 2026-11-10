<?php

declare(strict_types=1);

use App\Gamification\Application\Service\ProgressLeveler;
use App\Gamification\Application\UseCase\ApplyInactivityDecay\ApplyInactivityDecayHandler;
use App\Gamification\Application\UseCase\ApplyInactivityDecay\ApplyInactivityDecayMessage;
use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Rule\DelevelingPolicy;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\Clock\MockClock;

it('applies inactivity decay once per period', function () {
    /** @var UserRepositoryInterface $userRepository */
    $userRepository = $this->service(UserRepositoryInterface::class);
    /** @var UserProgressRepositoryInterface $progressRepository */
    $progressRepository = $this->service(UserProgressRepositoryInterface::class);
    /** @var XpEventRepositoryInterface $xpEventRepository */
    $xpEventRepository = $this->service(XpEventRepositoryInterface::class);
    /** @var EventDispatcherInterface $eventDispatcher */
    $eventDispatcher = $this->service(EventDispatcherInterface::class);

    $user = User::register(
        cpf: null,
        email: Email::fromString('decay@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Decay User',
    );
    $userRepository->save($user);

    $userId = PlayerId::fromString($user->id()->value());
    $progress = UserProgress::create($userId);
    $progress->award(1250, true);
    $progress->changeLevel(5);
    $progress->recordActivityValidation(
        Instant::fromString('2026-11-09T12:00:00+00:00')->value(),
    );
    $progressRepository->save($progress);

    $handler = new ApplyInactivityDecayHandler(
        progressRepository: $progressRepository,
        xpEventRepository: $xpEventRepository,
        delevelingPolicy: new DelevelingPolicy(new LevelCurve()),
        progressLeveler: new ProgressLeveler(new LevelCurve(), $eventDispatcher),
        eventDispatcher: $eventDispatcher,
        clock: new MockClock('2026-11-19T12:00:00+00:00'),
    );

    expect($handler(new ApplyInactivityDecayMessage()))->toBe(1);

    $decayed = $progressRepository->findByUserId($userId);

    expect($decayed)->not->toBeNull()
        ->and($decayed->xpBalance())->toBe(1240)
        ->and($decayed->level())->toBe(5)
        ->and($decayed->decayedPeriods())->toBe(1);

    expect($handler(new ApplyInactivityDecayMessage()))->toBe(0)
        ->and($progressRepository->findByUserId($userId)?->xpBalance())->toBe(1240);
});
