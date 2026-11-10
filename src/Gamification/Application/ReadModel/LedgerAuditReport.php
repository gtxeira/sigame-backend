<?php

declare(strict_types=1);

namespace App\Gamification\Application\ReadModel;

/**
 * Resultado da auditoria do ledger.
 */
final readonly class LedgerAuditReport
{
    /**
     * @param list<LedgerDivergence>     $divergences
     * @param list<LedgerIntegrityIssue> $integrityIssues
     */
    public function __construct(
        public array $divergences,
        public array $integrityIssues,
        public int $checkedUsers,
    ) {
    }

    public function isClean(): bool
    {
        return [] === $this->divergences && [] === $this->integrityIssues;
    }
}
