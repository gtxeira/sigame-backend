<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Repository;

use App\Identity\Domain\RefreshToken\Entity\RefreshToken;
use App\Identity\Domain\RefreshToken\Repository\RefreshTokenRepositoryInterface;
use App\Identity\Domain\RefreshToken\ValueObject\TokenFamilyId;
use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * Implementação Doctrine do repositório de refresh tokens.
 *
 * @extends AbstractRepository<RefreshToken>
 */
final class DoctrineRefreshTokenRepository extends AbstractRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function save(RefreshToken $token): void
    {
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
    }

    public function findByTokenHash(TokenHash $hash): ?RefreshToken
    {
        return $this->createQueryBuilder('r')
            ->where('r.tokenHash = :hash')
            ->setParameter('hash', $hash, 'token_hash')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function rotate(RefreshToken $current, RefreshToken $replacement): void
    {
        $entityManager = $this->getEntityManager();

        $rotate = function () use ($entityManager, $current, $replacement): void {
            $entityManager->persist($current);
            $entityManager->persist($replacement);
            $entityManager->flush();
        };

        // Em testes o DAMA já mantém uma transação aberta; abrir outra quebraria
        // o isolamento por rollback.
        if ($entityManager->getConnection()->isTransactionActive()) {
            $rotate();

            return;
        }

        $entityManager->wrapInTransaction($rotate);
    }

    public function revokeFamily(TokenFamilyId $familyId): int
    {
        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.revokedAt', ':now')
            ->where('r.familyId = :family')
            ->andWhere('r.revokedAt IS NULL')
            ->setParameter('now', Instant::now(), 'instant')
            ->setParameter('family', $familyId, 'token_family_id')
            ->getQuery()
            ->execute();
    }

    public function revokeAllForUser(UserId $userId): int
    {
        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.revokedAt', ':now')
            ->where('IDENTITY(r.user) = :userId')
            ->andWhere('r.revokedAt IS NULL')
            ->setParameter('now', Instant::now(), 'instant')
            ->setParameter('userId', $userId->value())
            ->getQuery()
            ->execute();
    }

    public function keepNewestFamilies(UserId $userId, int $keep): void
    {
        /** @var list<array{familyId: string}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('r.familyId AS familyId')
            ->where('IDENTITY(r.user) = :userId')
            ->andWhere('r.revokedAt IS NULL')
            ->andWhere('r.expiresAt > :now')
            ->groupBy('r.familyId')
            ->orderBy('MAX(r.createdAt)', SortDirection::Descending)
            ->setParameter('userId', $userId->value())
            ->setParameter('now', Instant::now(), 'instant')
            ->getQuery()
            ->getScalarResult();

        foreach (array_slice($rows, max(0, $keep)) as $row) {
            $this->revokeFamily(TokenFamilyId::fromString($row['familyId']));
        }
    }
}
