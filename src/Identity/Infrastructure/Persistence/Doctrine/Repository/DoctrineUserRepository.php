<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Repository;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Implementação Doctrine do repositório de usuários.
 *
 * @extends AbstractRepository<User>
 */
final class DoctrineUserRepository extends AbstractRepository implements UserRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findById(UserId $id): ?User
    {
        return $this->find($id->value());
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->findOneBy(['email' => $email->value()]);
    }

    public function findByCpf(Cpf $cpf): ?User
    {
        return $this->findOneBy(['cpf' => $cpf->value()]);
    }
}
