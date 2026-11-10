<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Persistence\Doctrine\Repository;

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Exception\MembershipAlreadyExistsException;
use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Groups\Domain\Group\Repository\GroupPage;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\Repository\GroupSearchCriteria;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * Implementação Doctrine do repositório de grupos.
 *
 * @extends AbstractRepository<Group>
 */
final class DoctrineGroupRepository extends AbstractRepository implements GroupRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function save(Group $group): void
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($group);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'uniq_group_memberships_active')) {
                throw MembershipAlreadyExistsException::create($exception);
            }

            throw $exception;
        }
    }

    public function findById(GroupId $id): ?Group
    {
        return $this->find($id->value());
    }

    public function findByIdForUpdate(GroupId $id): ?Group
    {
        return $this->createQueryBuilder('g')
            ->where('g.id = :id')
            ->setParameter('id', $id->value())
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function lockUser(MemberUserId $userId): void
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

    public function countApprovedMemberships(MemberUserId $userId): int
    {
        $count = $this->createQueryBuilder('g')
            ->select('COUNT(m.id)')
            ->innerJoin('g.memberships', 'm')
            ->where('m.userId = :userId')
            ->andWhere('m.status = :status')
            ->setParameter('userId', $userId, 'member_user_id')
            ->setParameter('status', MembershipStatus::APPROVED->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function search(GroupSearchCriteria $criteria): GroupPage
    {
        $pageQuery = $this->filteredQueryBuilder($criteria)
            ->orderBy('g.createdAt', SortDirection::Descending)
            ->addOrderBy('g.id', SortDirection::Descending)
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery();

        /** @var list<Group> $items */
        $items = $pageQuery->getResult();

        // A query filtra por agregado (HAVING COUNT), então o total é a
        // quantidade de grupos que sobrevive ao agrupamento.
        $total = count(
            $this->filteredQueryBuilder($criteria)
                ->select('g.id')
                ->getQuery()
                ->getSingleColumnResult(),
        );

        return new GroupPage(
            items: $items,
            total: $total,
        );
    }

    public function findApprovedByMember(MemberUserId $userId): array
    {
        /** @var list<Group> $groups */
        $groups = $this->createQueryBuilder('g')
            ->innerJoin('g.memberships', 'm')
            ->where('m.userId = :userId')
            ->andWhere('m.status = :status')
            ->setParameter('userId', $userId, 'member_user_id')
            ->setParameter('status', MembershipStatus::APPROVED->value)
            ->orderBy('g.createdAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();

        return $groups;
    }

    private function filteredQueryBuilder(GroupSearchCriteria $criteria): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('g');

        if (null !== $criteria->modality) {
            $queryBuilder
                ->andWhere('g.modality = :modality')
                ->setParameter('modality', $criteria->modality->value);
        }

        if (null !== $criteria->name && '' !== trim($criteria->name)) {
            $queryBuilder
                ->andWhere('LOWER(g.name) LIKE :name')
                ->setParameter('name', '%'.mb_strtolower(trim($criteria->name)).'%');
        }

        if (null === $criteria->status && !$criteria->availableOnly) {
            return $queryBuilder;
        }

        // Um único LEFT JOIN com a condição de status evita repetir subqueries
        // correlacionadas (e o alias duplicado que quebrava o DQL).
        $queryBuilder
            ->leftJoin('g.memberships', 'm', Join::WITH, 'm.status = :approvedStatus')
            ->setParameter('approvedStatus', MembershipStatus::APPROVED->value)
            ->groupBy('g.id');

        $approvedCount = 'COUNT(m.id)';
        $maxMembers = 'g.maxMembers';

        if (null !== $criteria->status) {
            match ($criteria->status) {
                GroupStatus::FORMING => $queryBuilder
                    ->andHaving($approvedCount.' < :minActive')
                    ->setParameter('minActive', GroupPolicy::MIN_APPROVED_FOR_ACTIVE),
                GroupStatus::ACTIVE => $queryBuilder
                    ->andHaving($approvedCount.' >= :minActive')
                    ->andHaving('('.$maxMembers.' IS NULL OR '.$approvedCount.' < '.$maxMembers.')')
                    ->setParameter('minActive', GroupPolicy::MIN_APPROVED_FOR_ACTIVE),
                GroupStatus::FULL => $queryBuilder
                    ->andHaving($maxMembers.' IS NOT NULL')
                    ->andHaving($approvedCount.' >= '.$maxMembers),
            };
        }

        if ($criteria->availableOnly) {
            $queryBuilder->andHaving('('.$maxMembers.' IS NULL OR '.$approvedCount.' < '.$maxMembers.')');
        }

        return $queryBuilder;
    }
}
