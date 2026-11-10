<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Persistence\Doctrine\Reader;

use App\Gamification\Application\ReadModel\XpLedgerTotals;
use App\Gamification\Application\Service\LedgerTotalsReaderInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Lê os totais do ledger com uma única agregação por usuário.
 *
 * O saldo soma todos os lançamentos. O estorno é um lançamento negativo e o
 * original permanece no ledger com `reversed_at`, então filtrar os revertidos
 * produziria um saldo errado.
 */
final readonly class DoctrineLedgerTotalsReader implements LedgerTotalsReaderInterface
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    /**
     * @return list<XpLedgerTotals>
     */
    public function totals(?string $userId = null): array
    {
        $sql = <<<'SQL'
            SELECT e.user_id AS user_id,
                   SUM(e.amount) AS balance,
                   SUM(
                       CASE
                           WHEN e.kind = ? THEN
                               CASE
                                   WHEN o.kind IS NOT NULL AND o.kind <> ? THEN e.amount
                                   ELSE 0
                               END
                           WHEN e.kind <> ? THEN e.amount
                           ELSE 0
                       END
                   ) AS lifetime
            FROM xp_events e
            LEFT JOIN xp_events o ON o.id = e.reversal_of_id
            SQL;

        $parameters = [
            XpKind::REVERSAL->value,
            XpKind::INACTIVITY_DECAY->value,
            XpKind::INACTIVITY_DECAY->value,
        ];

        if (null !== $userId) {
            $sql .= ' WHERE e.user_id = ?';
            $parameters[] = $userId;
        }

        $sql .= ' GROUP BY e.user_id ORDER BY e.user_id';

        $rows = $this->registry->getConnection()->fetchAllAssociative($sql, $parameters);

        return array_map(
            static fn (array $row): XpLedgerTotals => new XpLedgerTotals(
                userId: (string) $row['user_id'],
                balance: (int) $row['balance'],
                lifetime: (int) $row['lifetime'],
            ),
            $rows,
        );
    }
}
