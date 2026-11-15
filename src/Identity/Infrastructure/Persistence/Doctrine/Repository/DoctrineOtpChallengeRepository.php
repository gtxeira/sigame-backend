<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Repository;

use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpChallengeId;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Implementação Doctrine dos desafios OTP da identidade.
 *
 * @extends AbstractRepository<OtpChallenge>
 */
final class DoctrineOtpChallengeRepository extends AbstractRepository implements OtpChallengeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OtpChallenge::class);
    }

    public function save(OtpChallenge $challenge): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($challenge);
        $entityManager->flush();
    }

    public function findById(OtpChallengeId $id): ?OtpChallenge
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findActive(User $user, OtpPurpose $purpose): ?OtpChallenge
    {
        return $this->findOneBy([
            'user' => $user,
            'purpose' => $purpose,
            'consumedAt' => null,
        ]);
    }

    public function markConsumed(OtpChallenge $challenge): bool
    {
        $affected = $this->createQueryBuilder('c')
            ->update()
            ->set('c.consumedAt', ':now')
            ->where('c.id = :id')
            ->andWhere('c.consumedAt IS NULL')
            ->setParameter('now', Instant::now(), 'instant')
            ->setParameter('id', $challenge->id()->value())
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    public function recordFailedAttempt(OtpChallenge $challenge, int $maxAttempts): int
    {
        $attempts = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
                UPDATE identity_otp_challenges
                SET attempts = attempts + 1,
                    consumed_at = CASE
                        WHEN attempts + 1 >= :maxAttempts THEN :now
                        ELSE consumed_at
                    END
                WHERE id = :id AND consumed_at IS NULL
                RETURNING attempts
                SQL,
            [
                'maxAttempts' => $maxAttempts,
                'now' => Instant::now(),
                'id' => $challenge->id()->value(),
            ],
            [
                'now' => 'instant',
            ],
        );

        return false === $attempts ? $maxAttempts : (int) $attempts;
    }

    public function invalidateActive(User $user, OtpPurpose $purpose): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.consumedAt', ':now')
            ->where('IDENTITY(c.user) = :userId')
            ->andWhere('c.purpose = :purpose')
            ->andWhere('c.consumedAt IS NULL')
            ->setParameter('now', Instant::now(), 'instant')
            ->setParameter('userId', $user->id()->value())
            ->setParameter('purpose', $purpose->value)
            ->getQuery()
            ->execute();
    }

    public function hasActiveCreatedAfter(
        User $user,
        OtpPurpose $purpose,
        Instant $since,
    ): bool {
        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('IDENTITY(c.user) = :userId')
            ->andWhere('c.purpose = :purpose')
            ->andWhere('c.consumedAt IS NULL')
            ->andWhere('c.createdAt > :since')
            ->setParameter('userId', $user->id()->value())
            ->setParameter('purpose', $purpose->value)
            ->setParameter('since', $since, 'instant')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
