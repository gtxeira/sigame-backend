<?php

declare(strict_types=1);

use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Infrastructure\Console\CheckLevelProjectionCommand;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

it('repairs users.level from the xp balance', function () {
    $userRepository = $this->service(UserRepositoryInterface::class);
    $progressRepository = $this->service(UserProgressRepositoryInterface::class);
    $entityManager = $this->service(EntityManagerInterface::class);

    $user = ledgerTestUser($userRepository, 'projection-command@example.com');
    $playerId = PlayerId::fromString($user->id()->value());
    $progress = UserProgress::create($playerId);
    $progress->reconcile(100, 100);
    $progress->changeLevel(2);
    $progressRepository->save($progress);

    $tester = new CommandTester($this->service(CheckLevelProjectionCommand::class));

    expect($tester->execute([]))->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('projected=1 expected=2');

    expect($tester->execute(['--repair' => true]))->toBe(Command::SUCCESS);

    $entityManager->clear();
    $repairedUser = $userRepository->findById($user->id());
    $unchangedProgress = $progressRepository->findByUserId($playerId);

    expect($repairedUser?->level()->value())->toBe(2)
        ->and($unchangedProgress?->level())->toBe(2)
        ->and($unchangedProgress?->xpBalance())->toBe(100);
});
