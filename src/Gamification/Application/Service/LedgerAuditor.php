<?php

declare(strict_types=1);

namespace App\Gamification\Application\Service;

use App\Gamification\Application\ReadModel\LedgerAuditReport;
use App\Gamification\Application\ReadModel\LedgerDivergence;
use App\Gamification\Application\ReadModel\XpLedgerTotals;
use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Shared\Application\User\UserLevelReaderInterface;

/**
 * Compara o ledger com o progresso materializado e com a projeção de nível.
 */
final readonly class LedgerAuditor
{
    public function __construct(
        private LedgerTotalsReaderInterface $totalsReader,
        private LedgerIntegrityCheckerInterface $integrityChecker,
        private UserProgressRepositoryInterface $progressRepository,
        private LevelCurve $levelCurve,
        private UserLevelReaderInterface $levelReader,
    ) {
    }

    public function audit(?PlayerId $userId = null): LedgerAuditReport
    {
        $totals = [];

        foreach ($this->totalsReader->totals($userId?->value()) as $row) {
            $totals[$row->userId] = $row;
        }

        $progressRows = $this->progressRows($userId);
        $divergences = [];
        $seen = [];

        foreach ($progressRows as $progress) {
            $id = $progress->userId()->value();
            $seen[$id] = true;
            $ledger = $totals[$id] ?? new XpLedgerTotals($id, 0, 0);
            $divergence = $this->compare($progress, $ledger);

            if (!$divergence->isConsistent()) {
                $divergences[] = $divergence;
            }
        }

        foreach ($totals as $id => $ledger) {
            if (isset($seen[$id])) {
                continue;
            }

            $divergences[] = new LedgerDivergence(
                userId: $id,
                progressBalance: null,
                ledgerBalance: $ledger->balance,
                progressLifetime: null,
                ledgerLifetime: $ledger->lifetime,
                progressLevel: null,
                ledgerLevel: $this->levelCurve->levelFor($ledger->balance),
                projectedLevel: $this->levelReader->levelOf($id),
            );
        }

        return new LedgerAuditReport(
            divergences: $divergences,
            integrityIssues: $this->integrityChecker->issues($userId?->value()),
            checkedUsers: count(array_unique([...array_keys($seen), ...array_keys($totals)])),
        );
    }

    /**
     * @return list<UserProgress>
     */
    private function progressRows(?PlayerId $userId): array
    {
        if (null === $userId) {
            return $this->progressRepository->findAll();
        }

        $progress = $this->progressRepository->findByUserId($userId);

        return null === $progress ? [] : [$progress];
    }

    private function compare(UserProgress $progress, XpLedgerTotals $ledger): LedgerDivergence
    {
        return new LedgerDivergence(
            userId: $progress->userId()->value(),
            progressBalance: $progress->xpBalance(),
            ledgerBalance: $ledger->balance,
            progressLifetime: $progress->xpLifetime(),
            ledgerLifetime: $ledger->lifetime,
            progressLevel: $progress->level(),
            ledgerLevel: $this->levelCurve->levelFor($ledger->balance),
            projectedLevel: $this->levelReader->levelOf($progress->userId()->value()),
        );
    }
}
