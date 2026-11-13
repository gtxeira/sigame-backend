<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Persistence\Doctrine\Repository;

use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Exception\AttendanceAlreadyExistsException;
use App\Activities\Domain\Activity\Repository\ActivityPage;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\Repository\ActivitySearchCriteria;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Activities\Domain\Activity\ValueObject\AttendanceStatus;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * Implementação Doctrine do repositório de atividades.
 *
 * @extends AbstractRepository<Activity>
 */
final class DoctrineActivityRepository extends AbstractRepository implements ActivityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    public function save(Activity $activity): void
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($activity);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'uniq_activity_attendances_active')) {
                throw AttendanceAlreadyExistsException::create($exception);
            }

            throw $exception;
        }
    }

    public function findById(ActivityId $id): ?Activity
    {
        return $this->find($id->value());
    }

    public function findByIdForUpdate(ActivityId $id): ?Activity
    {
        return $this->createQueryBuilder('a')
            ->where('a.id = :id')
            ->setParameter('id', $id->value())
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function lockUser(ActivityUserId $userId): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [$userId->value()],
        );
    }

    public function transactional(callable $operation): mixed
    {
        $entityManager = $this->getEntityManager();

        if ($entityManager->getConnection()->isTransactionActive()) {
            return $operation();
        }

        return $entityManager->wrapInTransaction($operation);
    }

    public function searchByGroup(
        ActivityGroupId $groupId,
        ActivitySearchCriteria $criteria,
    ): ActivityPage {
        $queryBuilder = $this->createQueryBuilder('a')
            ->where('a.groupId = :groupId')
            ->setParameter('groupId', $groupId, 'activity_group_id');

        $this->applyFilters($queryBuilder, $criteria);

        $direction = $criteria->isDescending() ? SortDirection::Descending : SortDirection::Ascending;

        $items = $queryBuilder
            ->orderBy('a.startsAt', $direction)
            ->addOrderBy('a.id', $direction)
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult();

        $total = (int) $this->countQueryBuilder($groupId, null, $criteria)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return new ActivityPage(items: $items, total: $total);
    }

    public function searchByParticipant(
        ActivityUserId $userId,
        ActivitySearchCriteria $criteria,
    ): ActivityPage {
        $queryBuilder = $this->createQueryBuilder('a')
            ->innerJoin('a.attendances', 'attendance')
            ->where('attendance.userId = :userId')
            ->andWhere('attendance.status IN (:attendanceStatuses)')
            ->setParameter('userId', $userId, 'activity_user_id')
            ->setParameter('attendanceStatuses', [
                AttendanceStatus::ENROLLED->value,
                AttendanceStatus::CHECKED_IN->value,
                AttendanceStatus::VALIDATED->value,
            ]);

        $this->applyFilters($queryBuilder, $criteria);

        $direction = $criteria->isDescending() ? SortDirection::Descending : SortDirection::Ascending;

        $items = $queryBuilder
            ->orderBy('a.startsAt', $direction)
            ->addOrderBy('a.id', $direction)
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult();

        $total = (int) $this->countQueryBuilder(null, $userId, $criteria)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return new ActivityPage(items: $items, total: $total);
    }

    public function hasConflictingAttendance(
        ActivityUserId $userId,
        ActivityWindow $window,
        ?ActivityId $excludeActivityId = null,
    ): bool {
        $sql = <<<'SQL'
            SELECT 1
            FROM activity_attendances attendance
            INNER JOIN activities activity ON activity.id = attendance.activity_id
            WHERE attendance.user_id = :userId
              AND attendance.status IN ('enrolled', 'checked_in', 'validated')
              AND activity.status IN ('scheduled', 'started')
              AND activity.starts_at < :endsAt
              AND COALESCE(activity.auto_close_at, activity.ends_at) > :startsAt
            SQL;

        $parameters = [
            'userId' => $userId->value(),
            'startsAt' => $window->startsAt()->toIsoStringWithMicroseconds(),
            'endsAt' => $window->endsAt()->toIsoStringWithMicroseconds(),
        ];

        if (null !== $excludeActivityId) {
            $sql .= ' AND activity.id <> :excludeActivityId';
            $parameters['excludeActivityId'] = $excludeActivityId->value();
        }

        $sql .= ' LIMIT 1';

        return false !== $this->getEntityManager()->getConnection()->fetchOne($sql, $parameters);
    }

    public function conflictingParticipants(ActivityId $activityId, ActivityWindow $window): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT attendance.user_id
            FROM activity_attendances attendance
            WHERE attendance.activity_id = :activityId
              AND attendance.status IN ('enrolled', 'checked_in', 'validated')
              AND EXISTS (
                  SELECT 1
                  FROM activity_attendances other_attendance
                  INNER JOIN activities other_activity ON other_activity.id = other_attendance.activity_id
                  WHERE other_attendance.user_id = attendance.user_id
                    AND other_attendance.activity_id <> :activityId
                    AND other_attendance.status IN ('enrolled', 'checked_in', 'validated')
                    AND other_activity.status IN ('scheduled', 'started')
                    AND other_activity.starts_at < :endsAt
                    AND COALESCE(other_activity.auto_close_at, other_activity.ends_at) > :startsAt
              )
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'activityId' => $activityId->value(),
            'startsAt' => $window->startsAt()->toIsoStringWithMicroseconds(),
            'endsAt' => $window->endsAt()->toIsoStringWithMicroseconds(),
        ]);

        return array_map(static fn (mixed $value): string => (string) $value, $rows);
    }

    public function countCheckedIn(ActivityId $activityId): int
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(attendance.id)')
            ->innerJoin('a.attendances', 'attendance')
            ->where('a.id = :activityId')
            ->andWhere('attendance.status IN (:statuses)')
            ->setParameter('activityId', $activityId->value())
            ->setParameter('statuses', [
                AttendanceStatus::CHECKED_IN->value,
                AttendanceStatus::VALIDATED->value,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function findScheduledToAutoCancel(Instant $now): array
    {
        $threshold = Instant::fromDateTime($now->value()->modify('-180 minutes'));

        $rows = $this->createQueryBuilder('a')
            ->select('a.id')
            ->where('a.status = :status')
            ->andWhere('a.startsAt <= :threshold')
            ->setParameter('status', ActivityStatus::SCHEDULED->value)
            ->setParameter('threshold', $threshold, 'instant')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(
            static fn (mixed $value): ActivityId => ActivityId::fromString((string) $value),
            $rows,
        ));
    }

    public function findScheduledIdsByGroup(ActivityGroupId $groupId): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.id')
            ->where('a.groupId = :groupId')
            ->andWhere('a.status = :status')
            ->setParameter('groupId', $groupId, 'activity_group_id')
            ->setParameter('status', ActivityStatus::SCHEDULED->value)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(
            static fn (mixed $value): ActivityId => ActivityId::fromString((string) $value),
            $rows,
        ));
    }

    public function findStartedToAutoFinish(Instant $now): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.id')
            ->where('a.status = :status')
            ->andWhere('a.autoCloseAt IS NOT NULL')
            ->andWhere('a.autoCloseAt <= :now')
            ->setParameter('status', ActivityStatus::STARTED->value)
            ->setParameter('now', $now, 'instant')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(
            static fn (mixed $value): ActivityId => ActivityId::fromString((string) $value),
            $rows,
        ));
    }

    private function applyFilters(QueryBuilder $queryBuilder, ActivitySearchCriteria $criteria): void
    {
        if (null !== $criteria->status) {
            $queryBuilder
                ->andWhere('a.status = :status')
                ->setParameter('status', $criteria->status->value);
        }

        if (null !== $criteria->from) {
            $queryBuilder
                ->andWhere('a.startsAt >= :from')
                ->setParameter('from', $criteria->from, 'instant');
        }

        if (null !== $criteria->to) {
            $queryBuilder
                ->andWhere('a.startsAt <= :to')
                ->setParameter('to', $criteria->to, 'instant');
        }

        if (null !== $criteria->groupIds) {
            $queryBuilder
                ->andWhere('a.groupId IN (:groupIds)')
                ->setParameter('groupIds', $criteria->groupIds);
        }
    }

    private function countQueryBuilder(
        ?ActivityGroupId $groupId,
        ?ActivityUserId $userId,
        ActivitySearchCriteria $criteria,
    ): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('a');

        if (null !== $groupId) {
            $queryBuilder
                ->where('a.groupId = :groupId')
                ->setParameter('groupId', $groupId, 'activity_group_id');
        }

        if (null !== $userId) {
            $queryBuilder
                ->innerJoin('a.attendances', 'attendance')
                ->where('attendance.userId = :userId')
                ->andWhere('attendance.status IN (:attendanceStatuses)')
                ->setParameter('userId', $userId, 'activity_user_id')
                ->setParameter('attendanceStatuses', [
                    AttendanceStatus::ENROLLED->value,
                    AttendanceStatus::CHECKED_IN->value,
                    AttendanceStatus::VALIDATED->value,
                ]);
        }

        $this->applyFilters($queryBuilder, $criteria);

        return $queryBuilder;
    }
}
