<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Persistence\Doctrine\Repository;

use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends AbstractRepository<UserAchievement>
 */
final class DoctrineUserAchievementRepository extends AbstractRepository implements UserAchievementRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    /**
     * @return list<UserAchievement>
     */
    public function findByUserId(PlayerId $userId): array
    {
        return $this->createQueryBuilder('achievement')
            ->where('achievement.userId = :userId')
            ->setParameter('userId', $userId, 'player_id')
            ->orderBy('achievement.achievedAt', SortDirection::Ascending)
            ->addOrderBy('achievement.code', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    public function findByUserIdAndCode(PlayerId $userId, AchievementCode $code): ?UserAchievement
    {
        return $this->createQueryBuilder('achievement')
            ->where('achievement.userId = :userId')
            ->andWhere('achievement.code = :code')
            ->setParameter('userId', $userId, 'player_id')
            ->setParameter('code', $code->value)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(UserAchievement $achievement): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($achievement);
        $entityManager->flush();
    }

    public function insertIfAbsent(UserAchievement $achievement): bool
    {
        $affected = $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO user_achievements (user_id, code, achieved_at)
             VALUES (:userId, :code, :achievedAt)
             ON CONFLICT (user_id, code) DO NOTHING',
            [
                'userId' => $achievement->userId(),
                'code' => $achievement->code()->value,
                'achievedAt' => $achievement->achievedAt(),
            ],
            [
                'userId' => 'player_id',
                'achievedAt' => 'instant',
            ],
        );

        return 1 === $affected;
    }

    public function deleteByUserId(PlayerId $userId): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM '.UserAchievement::class.' achievement WHERE achievement.userId = :userId')
            ->setParameter('userId', $userId, 'player_id')
            ->execute();
    }
}
