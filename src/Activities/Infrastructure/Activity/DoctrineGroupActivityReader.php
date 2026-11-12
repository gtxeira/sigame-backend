<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Activity;

use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use App\Shared\Application\Activity\GroupActivityReaderInterface;
use App\Shared\Application\Activity\NextGroupActivity;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\EntityManagerInterface;
use SortDirection;

/**
 * Adapter Doctrine das leituras de atividade usadas pela listagem de grupos.
 *
 * - `nextForGroups`: atividade em andamento (`started`) ou prevista (`scheduled`)
 *   a partir de uma tolerância curta no passado — a primeira por horário vence.
 * - `groupIdsWithActivityInRange`: alimenta o filtro Todos/Hoje/Esta semana da Home.
 */
final readonly class DoctrineGroupActivityReader implements GroupActivityReaderInterface
{
    /** Atividade prevista que começou nos últimos minutos continua sendo "a próxima". */
    private const string SCHEDULE_GRACE = '-10 minutes';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function nextForGroups(array $groupIds): array
    {
        $ids = $this->normalizeIds($groupIds);

        if ([] === $ids) {
            return [];
        }

        $threshold = Instant::fromDateTime(Instant::now()->value()->modify(self::SCHEDULE_GRACE));

        /** @var list<Activity> $activities */
        $activities = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Activity::class, 'a')
            ->where('a.groupId IN (:groupIds)')
            ->andWhere('(a.status = :started OR (a.status = :scheduled AND a.startsAt >= :threshold))')
            ->setParameter('groupIds', $ids)
            ->setParameter('started', ActivityStatus::STARTED->value)
            ->setParameter('scheduled', ActivityStatus::SCHEDULED->value)
            ->setParameter('threshold', $threshold, 'instant')
            ->orderBy('a.startsAt', SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        $next = [];

        foreach ($activities as $activity) {
            $groupId = $activity->groupId()->value();

            // A ordenação por horário garante que a primeira seja a mais próxima.
            // Atividades em andamento começam no passado, então vêm antes das previstas.
            if (isset($next[$groupId])) {
                continue;
            }

            $next[$groupId] = new NextGroupActivity(
                groupId: $groupId,
                activityId: $activity->id()->value(),
                title: $activity->title()->value(),
                status: $activity->status()->value,
                startsAt: $activity->window()->startsAt()->toIsoStringWithMilliseconds(),
            );
        }

        return $next;
    }

    public function groupIdsWithActivityInRange(Instant $from, Instant $to): array
    {
        /** @var list<mixed> $ids */
        $ids = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT a.groupId')
            ->from(Activity::class, 'a')
            ->where('(a.status = :started AND a.startsAt < :to)')
            ->orWhere('(a.status = :scheduled AND a.startsAt >= :from AND a.startsAt < :to)')
            ->setParameter('started', ActivityStatus::STARTED->value)
            ->setParameter('scheduled', ActivityStatus::SCHEDULED->value)
            ->setParameter('from', $from, 'instant')
            ->setParameter('to', $to, 'instant')
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(
            static fn (mixed $id): string => $id instanceof ActivityGroupId ? $id->value() : (string) $id,
            $ids,
        );
    }

    /**
     * @param list<string> $groupIds
     *
     * @return list<string>
     */
    private function normalizeIds(array $groupIds): array
    {
        return array_values(array_unique(array_filter(
            $groupIds,
            static fn (string $id): bool => '' !== trim($id),
        )));
    }
}
