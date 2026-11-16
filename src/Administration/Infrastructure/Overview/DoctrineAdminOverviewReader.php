<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Overview;

use App\Administration\Application\Overview\AdminOverview;
use App\Administration\Application\Overview\AdminOverviewReaderInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Leitura consolidada por SQL das tabelas dos módulos donos.
 *
 * É um read model de relatório: não carrega agregados nem dispara eventos. Os
 * nomes de tabela/coluna são o contrato estável do schema já coberto por
 * migrations e pelo `SchemaDriftTest`.
 */
final readonly class DoctrineAdminOverviewReader implements AdminOverviewReaderInterface
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    public function overview(): AdminOverview
    {
        $connection = $this->registry->getConnection();

        $users = $connection->fetchAssociative(<<<'SQL'
            SELECT COUNT(*) AS total,
                   COUNT(*) FILTER (WHERE status = 'ACTIVE') AS active,
                   COUNT(*) FILTER (WHERE status = 'SUSPENDED') AS suspended,
                   COUNT(*) FILTER (WHERE role = 'ADMIN_SIGAME') AS admins
            FROM users
            SQL);

        $groups = $connection->fetchAssociative(<<<'SQL'
            SELECT COUNT(*) FILTER (WHERE archived_at IS NULL AND deleted_at IS NULL) AS active,
                   COUNT(*) FILTER (WHERE archived_at IS NOT NULL AND deleted_at IS NULL) AS archived,
                   COUNT(*) FILTER (WHERE deleted_at IS NOT NULL) AS deleted
            FROM groups
            SQL);

        $activities = $connection->fetchAssociative(<<<'SQL'
            SELECT COUNT(*) FILTER (WHERE status = 'scheduled') AS scheduled,
                   COUNT(*) FILTER (WHERE status = 'started') AS started,
                   COUNT(*) FILTER (WHERE status = 'finished') AS finished,
                   COUNT(*) FILTER (WHERE status = 'cancelled') AS cancelled,
                   COUNT(*) FILTER (WHERE status = 'invalidated') AS invalidated
            FROM activities
            SQL);

        $checkIns = $connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM activity_attendances
            WHERE checked_in_at IS NOT NULL
              AND checked_in_at >= NOW() - INTERVAL '7 days'
            SQL);

        return new AdminOverview(
            usersTotal: (int) ($users['total'] ?? 0),
            usersActive: (int) ($users['active'] ?? 0),
            usersSuspended: (int) ($users['suspended'] ?? 0),
            usersAdmins: (int) ($users['admins'] ?? 0),
            groupsActive: (int) ($groups['active'] ?? 0),
            groupsArchived: (int) ($groups['archived'] ?? 0),
            groupsDeleted: (int) ($groups['deleted'] ?? 0),
            activitiesScheduled: (int) ($activities['scheduled'] ?? 0),
            activitiesStarted: (int) ($activities['started'] ?? 0),
            activitiesFinished: (int) ($activities['finished'] ?? 0),
            activitiesCancelled: (int) ($activities['cancelled'] ?? 0),
            activitiesInvalidated: (int) ($activities['invalidated'] ?? 0),
            checkedInLast7Days: (int) $checkIns,
        );
    }
}
