<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Event;

use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Identity\Domain\User\Event\UserDeleted;
use App\Shared\Domain\Exception\InvalidUuidException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Remove as conquistas quando a conta é apagada.
 *
 * A tabela `user_achievements` não tem FK para `users` (o dado pertence a
 * Gamification), então a limpeza é feita aqui em vez de depender de cascata.
 */
#[AsEventListener(event: 'user.deleted')]
final readonly class DeleteAchievementsOnUserDeleted
{
    public function __construct(
        private UserAchievementRepositoryInterface $achievementRepository,
    ) {
    }

    public function __invoke(UserDeleted $event): void
    {
        try {
            $userId = PlayerId::fromString($event->userId());
        } catch (InvalidUuidException) {
            return;
        }

        $this->achievementRepository->deleteByUserId($userId);
    }
}
