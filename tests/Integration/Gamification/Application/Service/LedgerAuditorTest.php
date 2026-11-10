<?php

declare(strict_types=1);

use App\Gamification\Application\ReadModel\LedgerIntegrityIssue;
use App\Gamification\Application\Service\LedgerAuditor;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpEventId;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserLevel;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\EntityManagerInterface;

it('reports a consistent ledger', function () {
    $fixture = ledgerTestUserWithEvents(
        $this->service(UserRepositoryInterface::class),
        $this->service(UserProgressRepositoryInterface::class),
        $this->service(XpEventRepositoryInterface::class),
        'ledger-consistent@example.com',
        [
            ['kind' => XpKind::ONBOARDING, 'amount' => 100],
            ['kind' => XpKind::ACTIVITY, 'amount' => 35],
            ['kind' => XpKind::INACTIVITY_DECAY, 'amount' => -5],
        ],
    );

    $report = $this->service(LedgerAuditor::class)->audit($fixture['playerId']);

    expect($report->isClean())->toBeTrue()
        ->and($report->checkedUsers)->toBe(1)
        ->and($fixture['progress']->xpBalance())->toBe(130)
        ->and($fixture['progress']->xpLifetime())->toBe(135)
        ->and($fixture['progress']->level())->toBe(2);
});

it('detects materialized and projected divergences', function () {
    $userRepository = $this->service(UserRepositoryInterface::class);
    $entityManager = $this->service(EntityManagerInterface::class);

    $fixture = ledgerTestUserWithEvents(
        $userRepository,
        $this->service(UserProgressRepositoryInterface::class),
        $this->service(XpEventRepositoryInterface::class),
        'ledger-drift@example.com',
        [
            ['kind' => XpKind::ONBOARDING, 'amount' => 100],
            ['kind' => XpKind::ACTIVITY, 'amount' => 35],
            ['kind' => XpKind::INACTIVITY_DECAY, 'amount' => -5],
        ],
    );

    $entityManager->getConnection()->executeStatement(
        'UPDATE user_progress SET xp_balance = ?, xp_lifetime = ?, level = ? WHERE user_id = ?',
        [999, 999, 1, $fixture['playerId']->value()],
    );

    $fixture['user']->syncLevel(UserLevel::fromInt(1)->value());
    $userRepository->save($fixture['user']);
    $entityManager->clear();

    $report = $this->service(LedgerAuditor::class)->audit($fixture['playerId']);

    expect($report->isClean())->toBeFalse()
        ->and($report->divergences)->toHaveCount(1);

    $divergence = $report->divergences[0];

    expect($divergence->ledgerBalance)->toBe(130)
        ->and($divergence->progressBalance)->toBe(999)
        ->and($divergence->ledgerLifetime)->toBe(135)
        ->and($divergence->progressLifetime)->toBe(999)
        ->and($divergence->ledgerLevel)->toBe(2)
        ->and($divergence->progressLevel)->toBe(1)
        ->and($divergence->projectedLevel)->toBe(1);
});

it('detects ledger entries without user progress', function () {
    $user = ledgerTestUser(
        $this->service(UserRepositoryInterface::class),
        'ledger-without-progress@example.com',
    );
    $playerId = PlayerId::fromString($user->id()->value());

    ledgerTestRecordEvent(
        $this->service(XpEventRepositoryInterface::class),
        $playerId,
        XpKind::ACTIVITY,
        35,
        'ledger-without-progress',
    );

    $report = $this->service(LedgerAuditor::class)->audit($playerId);

    expect($report->isClean())->toBeFalse()
        ->and($report->divergences)->toHaveCount(1)
        ->and($report->divergences[0]->isProgressMissing())->toBeTrue();
});

it('detects broken ledger references', function () {
    $userRepository = $this->service(UserRepositoryInterface::class);
    $xpEventRepository = $this->service(XpEventRepositoryInterface::class);
    $user = ledgerTestUser($userRepository, 'ledger-integrity@example.com');
    $playerId = PlayerId::fromString($user->id()->value());

    ledgerTestRecordEvent(
        $xpEventRepository,
        $playerId,
        XpKind::REVERSAL,
        -35,
        'reversal-without-original',
        XpEventId::generate(),
    );

    $original = ledgerTestRecordEvent(
        $xpEventRepository,
        $playerId,
        XpKind::ACTIVITY,
        35,
        'reversed-without-reversal',
    );
    $original->markReversed(Instant::now());
    $xpEventRepository->save($original);

    $report = $this->service(LedgerAuditor::class)->audit($playerId);
    $types = array_map(
        static fn (LedgerIntegrityIssue $issue): string => $issue->type,
        $report->integrityIssues,
    );

    expect($types)->toContain(LedgerIntegrityIssue::REVERSAL_WITHOUT_ORIGINAL)
        ->and($types)->toContain(LedgerIntegrityIssue::REVERSED_WITHOUT_REVERSAL);
});
