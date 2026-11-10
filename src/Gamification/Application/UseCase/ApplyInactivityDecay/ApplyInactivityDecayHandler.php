<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\ApplyInactivityDecay;

use App\Gamification\Application\Service\ProgressLeveler;
use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Event\XpDecayed;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Rule\DelevelingPolicy;
use App\Gamification\Domain\Rule\GamificationRules;
use App\Gamification\Domain\Xp\Entity\XpEvent;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;

/**
 * Aplica os períodos completos de inatividade de forma idempotente.
 */
final readonly class ApplyInactivityDecayHandler
{
    public function __construct(
        private UserProgressRepositoryInterface $progressRepository,
        private XpEventRepositoryInterface $xpEventRepository,
        private DelevelingPolicy $delevelingPolicy,
        private ProgressLeveler $progressLeveler,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ApplyInactivityDecayMessage $message): int
    {
        $now = Instant::fromDateTime($this->clock->now());
        $before = Instant::fromDateTime($now->value()->modify(sprintf('-%d days', GamificationRules::DECAY_GRACE_DAYS)));

        $due = $this->progressRepository->findDueForDecay($before);
        $applied = 0;

        foreach ($due as $candidate) {
            $applied += $this->progressRepository->transactional(function () use ($candidate, $now): int {
                $progress = $this->progressRepository->findByUserIdForUpdate($candidate->userId());

                if (null === $progress || null === $progress->lastValidatedActivityAt()) {
                    return 0;
                }

                return $this->decay($progress, $now);
            });
        }

        return $applied;
    }

    private function decay(UserProgress $progress, Instant $now): int
    {
        $periods = $this->delevelingPolicy->completePeriods($progress->lastValidatedActivityAt(), $now);
        $processed = $progress->decayedPeriods();
        $applied = 0;

        for ($index = $processed + 1; $index <= $periods; ++$index) {
            $periodEnd = $this->periodEnd($progress, $index);
            $key = sprintf('decay:%s:%s', $progress->userId()->value(), $periodEnd);

            if (null !== $this->xpEventRepository->findActiveByIdempotencyKey($key)) {
                $progress->recordDecayPeriod($periodEnd);
                $this->progressRepository->save($progress);

                continue;
            }

            $result = $this->delevelingPolicy->apply($progress->xpBalance(), $progress->level(), 1);
            $penalty = $progress->xpBalance() - $result->xpBalance;

            if ($penalty <= 0) {
                break;
            }

            $event = XpEvent::record(
                userId: $progress->userId(),
                kind: XpKind::INACTIVITY_DECAY,
                amount: -$penalty,
                idempotencyKey: $key,
                sourceType: 'inactivity',
                sourceId: $periodEnd,
                metadata: ['periodEnd' => $periodEnd],
            );

            $this->xpEventRepository->save($event);
            $progress->decay($penalty);
            $progress->recordDecayPeriod($periodEnd);
            $this->progressLeveler->sync($progress, 'inactivity');
            $this->progressRepository->save($progress);

            $this->eventDispatcher->dispatch(XpDecayed::now(
                userId: $progress->userId()->value(),
                amount: -$penalty,
                balance: $progress->xpBalance(),
                level: $progress->level(),
                periodEnd: $periodEnd,
            ));

            ++$applied;
        }

        return $applied;
    }

    private function periodEnd(UserProgress $progress, int $index): string
    {
        $graceEnd = $progress->lastValidatedActivityAt()->timestamp()
            + (GamificationRules::DECAY_GRACE_DAYS * 86400);

        $periodEnd = $graceEnd + ($index * GamificationRules::DECAY_PERIOD_DAYS * 86400);

        return gmdate('Y-m-d', $periodEnd);
    }
}
