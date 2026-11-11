<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Repository;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Exception\CpfAlreadyExistsException;
use App\Identity\Domain\User\Exception\EmailAlreadyExistsException;
use App\Identity\Domain\User\Exception\SocialIdentityAlreadyLinkedException;
use App\Identity\Domain\User\Repository\UserPage;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSearchCriteria;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Domain\User\ValueObject\UserStatus;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

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
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $message = $exception->getMessage();

            if (str_contains($message, 'uniq_users_email')) {
                throw EmailAlreadyExistsException::create($exception);
            }

            if (str_contains($message, 'uniq_users_cpf')) {
                throw CpfAlreadyExistsException::create($exception);
            }

            if (str_contains($message, 'uniq_user_identities_provider_subject')) {
                throw SocialIdentityAlreadyLinkedException::create($exception);
            }

            throw $exception;
        }
    }

    public function delete(User $user): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function findById(UserId $id): ?User
    {
        return $this->find($id->value());
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $email, 'email')
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

    public function transactional(callable $operation): mixed
    {
        $entityManager = $this->getEntityManager();

        if ($entityManager->getConnection()->isTransactionActive()) {
            return $operation();
        }

        return $entityManager->wrapInTransaction($operation);
    }

    public function search(UserSearchCriteria $criteria): UserPage
    {
        $queryBuilder = $this->createQueryBuilder('u');

        if (null !== $criteria->query && '' !== trim($criteria->query)) {
            $term = '%'.mb_strtolower(trim($criteria->query)).'%';
            $queryBuilder
                ->andWhere('LOWER(u.profile.name) LIKE :query OR LOWER(u.email) LIKE :query')
                ->setParameter('query', $term);
        }

        if (null !== $criteria->status) {
            $queryBuilder
                ->andWhere('u.access.status = :status')
                ->setParameter('status', $criteria->status->value);
        }

        if (null !== $criteria->role) {
            $queryBuilder
                ->andWhere('u.access.role = :role')
                ->setParameter('role', $criteria->role->value);
        }

        $countQueryBuilder = clone $queryBuilder;
        $total = (int) $countQueryBuilder
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<User> $items */
        $items = $queryBuilder
            ->orderBy('u.createdAt', SortDirection::Descending)
            ->addOrderBy('u.id', SortDirection::Descending)
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult();

        return new UserPage(items: $items, total: $total);
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.access.role = :role')
            ->andWhere('u.access.status = :status')
            ->setParameter('role', UserRole::ADMIN_SIGAME->value)
            ->setParameter('status', UserStatus::ACTIVE->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
