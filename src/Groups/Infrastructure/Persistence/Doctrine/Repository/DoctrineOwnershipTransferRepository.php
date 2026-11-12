<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Persistence\Doctrine\Repository;

use App\Groups\Domain\Group\Entity\OwnershipTransfer;
use App\Groups\Domain\Group\Repository\OwnershipTransferRepositoryInterface;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Implementação Doctrine do histórico de responsabilidade dos grupos.
 *
 * @extends AbstractRepository<OwnershipTransfer>
 */
final class DoctrineOwnershipTransferRepository extends AbstractRepository implements OwnershipTransferRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OwnershipTransfer::class);
    }

    public function record(OwnershipTransfer $transfer): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($transfer);
        $entityManager->flush();
    }
}
