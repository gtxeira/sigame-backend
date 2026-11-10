<?php

declare(strict_types=1);

namespace App\Gamification\Application\ReadModel;

/**
 * Problema de integridade referencial do ledger.
 *
 * As FKs de `xp_events` foram removidas para manter o mapping alinhado ao
 * schema; estes problemas são reportados e não corrigidos automaticamente.
 */
final readonly class LedgerIntegrityIssue
{
    public const string REVERSAL_WITHOUT_ORIGINAL = 'reversal_without_original';
    public const string REVERSED_WITHOUT_REVERSAL = 'reversed_without_reversal';

    public function __construct(
        public string $type,
        public string $eventId,
        public string $userId,
    ) {
    }
}
