<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Persistence\Doctrine\Reader;

use App\Gamification\Application\ReadModel\LedgerIntegrityIssue;
use App\Gamification\Application\Service\LedgerIntegrityCheckerInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Verifica referências quebradas no ledger.
 *
 * Com as FKs removidas, o banco não impede um reversal sem original nem um
 * lançamento revertido sem o reversal correspondente.
 */
final readonly class DoctrineLedgerIntegrityChecker implements LedgerIntegrityCheckerInterface
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    /**
     * @return list<LedgerIntegrityIssue>
     */
    public function issues(?string $userId = null): array
    {
        return [
            ...$this->reversalsWithoutOriginal($userId),
            ...$this->reversedWithoutReversal($userId),
        ];
    }

    /**
     * @return list<LedgerIntegrityIssue>
     */
    private function reversalsWithoutOriginal(?string $userId): array
    {
        $sql = <<<'SQL'
            SELECT e.id AS id, e.user_id AS user_id
            FROM xp_events e
            LEFT JOIN xp_events o ON o.id = e.reversal_of_id
            WHERE e.kind = ? AND o.id IS NULL
            SQL;

        $parameters = [XpKind::REVERSAL->value];

        if (null !== $userId) {
            $sql .= ' AND e.user_id = ?';
            $parameters[] = $userId;
        }

        return $this->mapIssues(
            $this->registry->getConnection()->fetchAllAssociative($sql, $parameters),
            LedgerIntegrityIssue::REVERSAL_WITHOUT_ORIGINAL,
        );
    }

    /**
     * @return list<LedgerIntegrityIssue>
     */
    private function reversedWithoutReversal(?string $userId): array
    {
        $sql = <<<'SQL'
            SELECT e.id AS id, e.user_id AS user_id
            FROM xp_events e
            LEFT JOIN xp_events r ON r.reversal_of_id = e.id AND r.kind = ?
            WHERE e.reversed_at IS NOT NULL AND r.id IS NULL
            SQL;

        $parameters = [XpKind::REVERSAL->value];

        if (null !== $userId) {
            $sql .= ' AND e.user_id = ?';
            $parameters[] = $userId;
        }

        return $this->mapIssues(
            $this->registry->getConnection()->fetchAllAssociative($sql, $parameters),
            LedgerIntegrityIssue::REVERSED_WITHOUT_REVERSAL,
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<LedgerIntegrityIssue>
     */
    private function mapIssues(array $rows, string $type): array
    {
        return array_map(
            static fn (array $row): LedgerIntegrityIssue => new LedgerIntegrityIssue(
                type: $type,
                eventId: (string) $row['id'],
                userId: (string) $row['user_id'],
            ),
            $rows,
        );
    }
}
