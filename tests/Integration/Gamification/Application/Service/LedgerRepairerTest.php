<?php

declare(strict_types=1);

use App\Gamification\Application\Service\LedgerAuditor;
use App\Gamification\Application\Service\LedgerRepairer;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserLevel;
use Doctrine\ORM\EntityManagerInterface;

it('repairs the materialized progress and the level projection', function () {
    $userRepository = $this->service(UserRepositoryInterface::class);
    $progressRepository = $this->service(UserProgressRepositoryInterface::class);
    $entityManager = $this->service(EntityManagerInterface::class);

    $fixture = ledgerTestUserWithEvents(
        $userRepository,
        $progressRepository,
        $this->service(XpEventRepositoryInterface::class),
        'ledger-repair@example.com',
        [
            ['kind' => XpKind::ONBOARDING, 'amount' => 100],
            ['kind' => XpKind::ACTIVITY, 'amount' => 35],
            ['kind' => XpKind::INACTIVITY_DECAY, 'amount' => -5],
        ],
    );

    $entityManager->getConnection()->executeStatement(
        'UPDATE user_progress SET xp_balance = ?, xp_lifetime = ?, level = ? WHERE user_id = ?',
        [1, 1, 1, $fixture['playerId']->value()],
    );

    $fixture['user']->syncLevel(UserLevel::fromInt(1)->value());
    $userRepository->save($fixture['user']);
    $entityManager->clear();

    $this->service(LedgerRepairer::class)->repair($fixture['playerId']->value());
    $this->service(LedgerRepairer::class)->repair($fixture['playerId']->value());
    $entityManager->clear();

    $progress = $progressRepository->findByUserId($fixture['playerId']);
    $user = $userRepository->findById($fixture['user']->id());

    expect($progress)->not->toBeNull()
        ->and($progress?->xpBalance())->toBe(130)
        ->and($progress?->xpLifetime())->toBe(135)
        ->and($progress?->level())->toBe(2)
        ->and($user?->level()->value())->toBe(2);

    expect($this->service(LedgerAuditor::class)->audit($fixture['playerId'])->isClean())->toBeTrue();
});

it('creates the missing progress while repairing', function () {
    $userRepository = $this->service(UserRepositoryInterface::class);
    $progressRepository = $this->service(UserProgressRepositoryInterface::class);
    $entityManager = $this->service(EntityManagerInterface::class);

    $user = ledgerTestUser($userRepository, 'ledger-repair-create@example.com');
    $playerId = PlayerId::fromString($user->id()->value());

    ledgerTestRecordEvent(
        $this->service(XpEventRepositoryInterface::class),
        $playerId,
        XpKind::ONBOARDING,
        100,
        'ledger-repair-create',
    );

    $this->service(LedgerRepairer::class)->repair($playerId->value());
    $entityManager->clear();

    $progress = $progressRepository->findByUserId($playerId);
    $repairedUser = $userRepository->findById($user->id());

    expect($progress)->not->toBeNull()
        ->and($progress?->xpBalance())->toBe(100)
        ->and($progress?->xpLifetime())->toBe(100)
        ->and($progress?->level())->toBe(2)
        ->and($repairedUser?->level()->value())->toBe(2);

    expect($this->service(LedgerAuditor::class)->audit($playerId)->isClean())->toBeTrue();
});
