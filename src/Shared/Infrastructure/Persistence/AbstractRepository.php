<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repositório base para todos os repositórios Doctrine do projeto.
 *
 * Estende ServiceEntityRepository para ter acesso ao EntityRepository do Doctrine.
 * Cada módulo pode estender esta classe para seus repositórios específicos.
 *
 * @template T of object
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class AbstractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * Obtém o Entity Manager atual.
     */
    protected function entityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        return $this->getEntityManager();
    }
}
