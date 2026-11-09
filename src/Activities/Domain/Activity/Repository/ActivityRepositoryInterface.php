<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Repository;

use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Porta de persistência do agregado Activity.
 */
interface ActivityRepositoryInterface
{
    public function save(Activity $activity): void;

    public function findById(ActivityId $id): ?Activity;

    /**
     * Carrega a atividade com lock pessimista para decisões concorrentes.
     */
    public function findByIdForUpdate(ActivityId $id): ?Activity;

    /**
     * Serializa operações do mesmo usuário entre atividades distintas.
     */
    public function lockUser(ActivityUserId $userId): void;

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed;

    public function searchByGroup(
        ActivityGroupId $groupId,
        ActivitySearchCriteria $criteria,
    ): ActivityPage;

    public function searchByParticipant(
        ActivityUserId $userId,
        ActivitySearchCriteria $criteria,
    ): ActivityPage;

    public function hasConflictingAttendance(
        ActivityUserId $userId,
        ActivityWindow $window,
        ?ActivityId $excludeActivityId = null,
    ): bool;

    /**
     * @return list<string> IDs dos usuários inscritos que entrariam em conflito
     */
    public function conflictingParticipants(ActivityId $activityId, ActivityWindow $window): array;

    public function countCheckedIn(ActivityId $activityId): int;

    /**
     * @return list<ActivityId>
     */
    public function findScheduledToAutoCancel(Instant $now): array;

    /**
     * @return list<ActivityId>
     */
    public function findStartedToAutoFinish(Instant $now): array;
}
