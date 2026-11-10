<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Repository;

use App\Identity\Domain\User\Entity\UserSuspension;
use App\Identity\Domain\User\Repository\UserSuspensionRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * Implementação Doctrine do histórico de suspensões.
 *
 * @extends AbstractRepository<UserSuspension>
 */
final class DoctrineUserSuspensionRepository extends AbstractRepository implements UserSuspensionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSuspension::class);
    }

    public function save(UserSuspension $suspension): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($suspension);
        $entityManager->flush();
    }

    public function findByUser(UserId $userId, int $limit = 50, int $offset = 0): array
    {
        /** @var list<UserSuspension> $suspensions */
        $suspensions = $this->createQueryBuilder('s')
            ->where('IDENTITY(s.user) = :userId')
            ->setParameter('userId', $userId->value())
            ->orderBy('s.occurredAt', SortDirection::Descending)
            ->addOrderBy('s.id', SortDirection::Descending)
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $suspensions;
    }

    public function countByUser(UserId $userId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('IDENTITY(s.user) = :userId')
            ->setParameter('userId', $userId->value())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
