<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Repository;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Identity\Domain\User\ValueObject\UserId;
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
        return $this->createQueryBuilder('u')
            ->innerJoin('u.identities', 'i')
            ->where('i.email = :email')
            ->setParameter('email', $email, 'email')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCpf(Cpf $cpf): ?User
    {
        return $this->findOneBy(['cpf' => $cpf->value()]);
    }

    public function findByProviderSubject(
        IdentityProvider $provider,
        ProviderSubject $subject,
    ): ?User {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.identities', 'i')
            ->where('i.provider = :provider')
            ->andWhere('i.providerSubject = :subject')
            ->setParameter('provider', $provider->value)
            ->setParameter('subject', $subject, 'provider_subject')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
