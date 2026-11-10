<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Event;

use App\Gamification\Application\Service\ProgressLeveler;
use App\Gamification\Application\Service\ProgressProvider;
use App\Gamification\Domain\Progress\Event\XpAwarded;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Rule\GamificationRules;
use App\Gamification\Domain\Xp\Entity\XpEvent;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Identity\Domain\User\Event\OnboardingCompleted;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Exception\InvalidUuidException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Concede o XP único de onboarding, suficiente para o nível 2.
 */
#[AsEventListener(event: 'onboarding.completed')]
final readonly class GrantOnboardingXpOnOnboardingCompleted
{
    public function __construct(
        private ProgressProvider $progressProvider,
        private UserProgressRepositoryInterface $progressRepository,
        private XpEventRepositoryInterface $xpEventRepository,
        private ProgressLeveler $progressLeveler,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(OnboardingCompleted $event): void
    {
        try {
            $userId = PlayerId::fromString($event->userId());
        } catch (InvalidUuidException) {
            return;
        }

        $progress = $this->progressProvider->getOrCreate($userId);
        $key = sprintf('onboarding:%s', $userId->value());

        if (null !== $this->xpEventRepository->findActiveByIdempotencyKey($key)) {
            $this->progressRepository->save($progress);

            return;
        }

        $xpEvent = XpEvent::record(
            userId: $userId,
            kind: XpKind::ONBOARDING,
            amount: GamificationRules::ONBOARDING_XP,
            idempotencyKey: $key,
            sourceType: 'onboarding',
            sourceId: $userId->value(),
        );

        $this->xpEventRepository->save($xpEvent);
        $progress->award(GamificationRules::ONBOARDING_XP, true);
        $this->progressLeveler->sync($progress);
        $this->progressRepository->save($progress);

        $this->eventDispatcher->dispatch(XpAwarded::now(
            userId: $userId->value(),
            amount: GamificationRules::ONBOARDING_XP,
            kind: XpKind::ONBOARDING,
            balance: $progress->xpBalance(),
            level: $progress->level(),
        ));
    }
}
