<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Repository;

use App\Administration\Domain\Audit\Entity\AdminAuditEntry;
use App\Administration\Domain\Audit\Repository\AdminAuditPage;
use App\Administration\Domain\Audit\Repository\AdminAuditRepositoryInterface;
use App\Administration\Domain\Audit\Repository\AdminAuditSearchCriteria;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * Implementação Doctrine da trilha de auditoria.
 *
 * @extends AbstractRepository<AdminAuditEntry>
 */
final class DoctrineAdminAuditRepository extends AbstractRepository implements AdminAuditRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminAuditEntry::class);
    }

    public function append(AdminAuditEntry $entry): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($entry);
        $entityManager->flush();
    }

    public function search(AdminAuditSearchCriteria $criteria): AdminAuditPage
    {
        $queryBuilder = $this->filteredQueryBuilder($criteria);

        /** @var list<AdminAuditEntry> $items */
        $items = $queryBuilder
            ->orderBy('a.occurredAt', SortDirection::Descending)
            ->addOrderBy('a.id', SortDirection::Descending)
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult();

        $total = (int) $this->filteredQueryBuilder($criteria)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return new AdminAuditPage(items: $items, total: $total);
    }

    private function filteredQueryBuilder(AdminAuditSearchCriteria $criteria): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('a');

        if (null !== $criteria->action) {
            $queryBuilder
                ->andWhere('a.action = :action')
                ->setParameter('action', $criteria->action->value);
        }

        if (null !== $criteria->targetType) {
            $queryBuilder
                ->andWhere('a.targetType = :targetType')
                ->setParameter('targetType', $criteria->targetType->value);
        }

        if (null !== $criteria->actorId && '' !== $criteria->actorId) {
            $queryBuilder
                ->andWhere('a.actorId = :actorId')
                ->setParameter('actorId', $criteria->actorId);
        }

        if (null !== $criteria->from) {
            $queryBuilder
                ->andWhere('a.occurredAt >= :from')
                ->setParameter('from', $criteria->from, 'instant');
        }

        if (null !== $criteria->to) {
            $queryBuilder
                ->andWhere('a.occurredAt <= :to')
                ->setParameter('to', $criteria->to, 'instant');
        }

        return $queryBuilder;
    }
}
