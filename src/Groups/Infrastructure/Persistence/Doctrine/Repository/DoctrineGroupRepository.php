<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Persistence\Doctrine\Repository;

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Exception\DuplicateGroupException;
use App\Groups\Domain\Group\Exception\MembershipAlreadyExistsException;
use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Groups\Domain\Group\Repository\GroupPage;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\Repository\GroupSearchCriteria;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Domain\ValueObject\Modality;
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

            if (str_contains($exception->getMessage(), 'uniq_groups_owner_name_modality')) {
                throw DuplicateGroupException::create($exception);
            }

            throw $exception;
        }
    }

    public function findById(GroupId $id): ?Group
    {
        return $this->find($id->value());
    }

    public function findByIdExcludingDeleted(GroupId $id): ?Group
    {
        $group = $this->findById($id);

        return null !== $group && $group->isDeleted() ? null : $group;
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
            ->andWhere('g.archivedAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId, 'member_user_id')
            ->setParameter('status', MembershipStatus::APPROVED->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function countFormingOwnedBy(MemberUserId $userId): int
    {
        $count = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.ownerId = :userId')
            ->andWhere('g.activatedAt IS NULL')
            ->andWhere('g.archivedAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId, 'member_user_id')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function findMembershipsOfUser(MemberUserId $userId): array
    {
        /** @var list<Membership> $memberships */
        $memberships = $this->getEntityManager()->createQueryBuilder()
            ->select('m', 'g')
            ->from(Membership::class, 'm')
            ->innerJoin('m.group', 'g')
            ->where('m.userId = :userId')
            // O vínculo do criador não é uma solicitação de entrada.
            ->andWhere('g.createdBy <> :userId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId, 'member_user_id')
            ->orderBy('m.requestedAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();

        return $memberships;
    }

    public function existsByOwnerAndName(
        MemberUserId $owner,
        GroupName $name,
        Modality $modality,
        ?GroupId $excludeGroupId = null,
    ): bool {
        $queryBuilder = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.ownerId = :owner')
            ->andWhere('LOWER(g.name) = :name')
            ->andWhere('g.modality = :modality')
            ->andWhere('g.archivedAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('owner', $owner, 'member_user_id')
            ->setParameter('name', mb_strtolower($name->value()))
            ->setParameter('modality', $modality->value);

        if (null !== $excludeGroupId) {
            $queryBuilder
                ->andWhere('g.id <> :excludeGroupId')
                ->setParameter('excludeGroupId', $excludeGroupId, 'group_id');
        }

        $count = $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }

    public function findIdleFormingGroupIds(Instant $idleSince): array
    {
        /** @var list<Group> $groups */
        $groups = $this->createQueryBuilder('g')
            ->where('g.activatedAt IS NULL')
            ->andWhere('g.archivedAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('g.updatedAt < :idleSince')
            ->setParameter('idleSince', $idleSince, 'instant')
            ->orderBy('g.updatedAt', SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return array_map(static fn (Group $group): GroupId => $group->id(), $groups);
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
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId, 'member_user_id')
            ->setParameter('status', MembershipStatus::APPROVED->value)
            ->orderBy('g.createdAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();

        return $groups;
    }

    private function filteredQueryBuilder(GroupSearchCriteria $criteria): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('g')
            ->andWhere('g.archivedAt IS NULL')
            ->andWhere('g.deletedAt IS NULL');

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

        if (null !== $criteria->activityGroupIds) {
            // Filtro pela data das atividades (Home: Todos/Hoje/Esta semana).
            // Os ids vêm da porta compartilhada `GroupActivityReaderInterface`;
            // aqui só restringimos a consulta, sem importar o módulo Activities.
            if ([] === $criteria->activityGroupIds) {
                $queryBuilder->andWhere('1 = 0');
            } else {
                $queryBuilder
                    ->andWhere('g.id IN (:activityGroupIds)')
                    ->setParameter('activityGroupIds', $criteria->activityGroupIds);
            }
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
