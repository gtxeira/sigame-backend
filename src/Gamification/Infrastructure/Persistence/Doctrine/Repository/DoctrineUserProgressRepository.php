<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Persistence\Doctrine\Repository;

use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends AbstractRepository<UserProgress>
 */
final class DoctrineUserProgressRepository extends AbstractRepository implements UserProgressRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserProgress::class);
    }

    public function findByUserId(PlayerId $userId): ?UserProgress
    {
        return $this->find($userId->value());
    }

    public function findByUserIdForUpdate(PlayerId $userId): ?UserProgress
    {
        return $this->createQueryBuilder('p')
            ->where('p.userId = :userId')
            ->setParameter('userId', $userId, 'player_id')
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function save(UserProgress $progress): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($progress);
        $entityManager->flush();
    }

    /**
     * @return list<UserProgress>
     */
    public function findDueForDecay(Instant $before): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.lastValidatedActivityAt IS NOT NULL')
            ->andWhere('p.lastValidatedActivityAt <= :before')
            ->andWhere('p.level > 1')
            ->setParameter('before', $before, 'instant')
            ->orderBy('p.userId', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<UserProgress>
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.userId', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    public function transactional(callable $operation): mixed
    {
        $entityManager = $this->getEntityManager();

        if ($entityManager->getConnection()->isTransactionActive()) {
            return $operation();
        }

        return $entityManager->wrapInTransaction($operation);
    }
}
