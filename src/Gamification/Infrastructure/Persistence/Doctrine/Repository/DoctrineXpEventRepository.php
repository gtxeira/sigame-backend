<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Persistence\Doctrine\Repository;

use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Xp\Entity\XpEvent;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends AbstractRepository<XpEvent>
 */
final class DoctrineXpEventRepository extends AbstractRepository implements XpEventRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, XpEvent::class);
    }

    public function save(XpEvent $event): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($event);
        $entityManager->flush();
    }

    public function findActiveByIdempotencyKey(string $key): ?XpEvent
    {
        return $this->createQueryBuilder('e')
            ->where('e.idempotencyKey = :key')
            ->andWhere('e.reversedAt IS NULL')
            ->setParameter('key', $key)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveWeeklyEvent(PlayerId $userId, string $weekKey, XpKind $kind): ?XpEvent
    {
        return $this->createQueryBuilder('e')
            ->where('e.userId = :userId')
            ->andWhere('e.weekKey = :weekKey')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.reversedAt IS NULL')
            ->setParameter('userId', $userId, 'player_id')
            ->setParameter('weekKey', $weekKey)
            ->setParameter('kind', $kind->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<XpEvent>
     */
    public function findActiveByActivityAndUser(string $activityId, PlayerId $userId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.activityId = :activityId')
            ->andWhere('e.userId = :userId')
            ->andWhere('e.kind != :reversalKind')
            ->andWhere('e.reversedAt IS NULL')
            ->setParameter('activityId', $activityId)
            ->setParameter('userId', $userId, 'player_id')
            ->setParameter('reversalKind', XpKind::REVERSAL->value)
            ->orderBy('e.createdAt', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    public function countActiveActivityEventsOnDate(PlayerId $userId, string $activityDate): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.userId = :userId')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.activityDate = :activityDate')
            ->andWhere('e.reversedAt IS NULL')
            ->setParameter('userId', $userId, 'player_id')
            ->setParameter('kind', XpKind::ACTIVITY->value)
            ->setParameter('activityDate', $activityDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    public function findActiveActivityDates(PlayerId $userId): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('DISTINCT e.activityDate')
            ->where('e.userId = :userId')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.activityDate IS NOT NULL')
            ->andWhere('e.reversedAt IS NULL')
            ->setParameter('userId', $userId, 'player_id')
            ->setParameter('kind', XpKind::ACTIVITY->value)
            ->orderBy('e.activityDate', SortDirection::Ascending)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['activityDate'],
            $rows,
        ));
    }

    /**
     * @return list<XpEvent>
     */
    public function findActiveActivityEvents(PlayerId $userId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.userId = :userId')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.activityId IS NOT NULL')
            ->andWhere('e.reversedAt IS NULL')
            ->setParameter('userId', $userId, 'player_id')
            ->setParameter('kind', XpKind::ACTIVITY->value)
            ->orderBy('e.activityDate', SortDirection::Ascending)
            ->addOrderBy('e.createdAt', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<XpEvent>
     */
    public function findHistory(PlayerId $userId, int $page, int $perPage): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.userId = :userId')
            ->setParameter('userId', $userId, 'player_id')
            ->orderBy('e.createdAt', SortDirection::Descending)
            ->addOrderBy('e.id', SortDirection::Descending)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countHistory(PlayerId $userId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.userId = :userId')
            ->setParameter('userId', $userId, 'player_id')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
