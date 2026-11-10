<?php

declare(strict_types=1);

use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Gamification\Infrastructure\Console\AuditLedgerCommand;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

it('fails without repair and fixes divergences with repair', function () {
    $progressRepository = $this->service(UserProgressRepositoryInterface::class);
    $fixture = ledgerTestUserWithEvents(
        $this->service(UserRepositoryInterface::class),
        $progressRepository,
        $this->service(XpEventRepositoryInterface::class),
        'audit-command@example.com',
        [
            ['kind' => XpKind::ONBOARDING, 'amount' => 100],
            ['kind' => XpKind::ACTIVITY, 'amount' => 35],
            ['kind' => XpKind::INACTIVITY_DECAY, 'amount' => -5],
        ],
    );

    $entityManager = $this->service(EntityManagerInterface::class);
    $entityManager->getConnection()->executeStatement(
        'UPDATE user_progress SET xp_balance = xp_balance + 10 WHERE user_id = ?',
        [$fixture['playerId']->value()],
    );
    $entityManager->clear();

    $tester = new CommandTester($this->service(AuditLedgerCommand::class));

    expect($tester->execute(['--user-id' => $fixture['playerId']->value()]))->toBe(Command::FAILURE);
    expect($tester->getDisplay())->toContain('balance progress=140 ledger=130');

    $entityManager->clear();
    expect($progressRepository->findByUserId($fixture['playerId'])?->xpBalance())->toBe(140);

    expect($tester->execute([
        '--user-id' => $fixture['playerId']->value(),
        '--repair' => true,
    ]))->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('Ledger reparado e consistente');

    $entityManager->clear();
    $repaired = $progressRepository->findByUserId($fixture['playerId']);

    expect($repaired?->xpBalance())->toBe(130)
        ->and($repaired?->xpLifetime())->toBe(135)
        ->and($repaired?->level())->toBe(2);
});
