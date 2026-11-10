<?php

declare(strict_types=1);

namespace App\Gamification\Application\Service;

use App\Gamification\Application\ReadModel\XpLedgerTotals;
use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Shared\Application\User\UserLevelProjectionInterface;
use LogicException;
use Psr\Log\LoggerInterface;

/**
 * Corrige o progresso materializado e a projeção de nível a partir do ledger.
 *
 * O reparo não grava evento de XP: ele apenas alinha a materialização ao que o
 * ledger já registra. O valor é relido dentro da transação para não sobrescrever
 * uma concessão concorrente com um total antigo.
 */
final readonly class LedgerRepairer
{
    public function __construct(
        private UserProgressRepositoryInterface $progressRepository,
        private LedgerTotalsReaderInterface $totalsReader,
        private LevelCurve $levelCurve,
        private UserLevelProjectionInterface $projection,
        private LoggerInterface $logger,
    ) {
    }

    public function repair(string $userId): void
    {
        $playerId = PlayerId::fromString($userId);

        $this->progressRepository->transactional(function () use ($playerId, $userId): void {
            $progress = $this->progressRepository->findByUserIdForUpdate($playerId)
                ?? UserProgress::create($playerId);

            $ledger = $this->totalsReader->totals($userId)[0]
                ?? new XpLedgerTotals($userId, 0, 0);

            if ($ledger->balance < 0 || $ledger->lifetime < 0) {
                throw new LogicException(sprintf(
                    'O ledger do usuário %s tem valores negativos e não pode ser reparado automaticamente.',
                    $userId,
                ));
            }

            $level = $this->levelCurve->levelFor($ledger->balance);
            $before = [
                'balance' => $progress->xpBalance(),
                'lifetime' => $progress->xpLifetime(),
                'level' => $progress->level(),
            ];

            $progress->reconcile($ledger->balance, $ledger->lifetime);
            $progress->changeLevel($level);
            $this->progressRepository->save($progress);

            $projected = $this->projection->project($userId, $level);

            $this->logger->info('gamification.ledger.repaired', [
                'userId' => $userId,
                'before' => $before,
                'after' => [
                    'balance' => $ledger->balance,
                    'lifetime' => $ledger->lifetime,
                    'level' => $level,
                ],
                'projectionUpdated' => $projected,
            ]);
        });
    }
}
