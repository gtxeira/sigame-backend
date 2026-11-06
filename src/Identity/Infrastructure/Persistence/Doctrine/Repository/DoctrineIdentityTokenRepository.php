<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Repository;

use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Implementação Doctrine dos tokens de uso único da identidade.
 *
 * @extends AbstractRepository<IdentityToken>
 */
final class DoctrineIdentityTokenRepository extends AbstractRepository implements IdentityTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdentityToken::class);
    }

    public function save(IdentityToken $token): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($token);
        $entityManager->flush();
    }

    public function findByHash(string $tokenHash): ?IdentityToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function markConsumed(IdentityToken $token): bool
    {
        $affected = $this->createQueryBuilder('t')
            ->update()
            ->set('t.consumedAt', ':now')
            ->where('t.id = :id')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('now', Instant::now(), 'instant')
            ->setParameter('id', $token->id()->value())
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    public function invalidateUnconsumed(User $user, IdentityTokenPurpose $purpose): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.consumedAt', ':now')
            ->where('IDENTITY(t.user) = :userId')
            ->andWhere('t.purpose = :purpose')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('now', Instant::now(), 'instant')
            ->setParameter('userId', $user->id()->value())
            ->setParameter('purpose', $purpose->value)
            ->getQuery()
            ->execute();
    }

    public function hasUnconsumedCreatedAfter(
        User $user,
        IdentityTokenPurpose $purpose,
        Instant $since,
    ): bool {
        $count = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('IDENTITY(t.user) = :userId')
            ->andWhere('t.purpose = :purpose')
            ->andWhere('t.consumedAt IS NULL')
            ->andWhere('t.createdAt > :since')
            ->setParameter('userId', $user->id()->value())
            ->setParameter('purpose', $purpose->value)
            ->setParameter('since', $since, 'instant')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
