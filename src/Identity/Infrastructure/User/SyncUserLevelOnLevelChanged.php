<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\User;

use App\Gamification\Domain\Progress\Event\LevelLost;
use App\Gamification\Domain\Progress\Event\LevelReached;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Domain\User\ValueObject\UserLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;

/**
 * Projeta o nível calculado pela gamificação em `users.level`.
 *
 * O nível é usado por Groups e pela segurança. A operação é síncrona e ocorre
 * na mesma transação do evento; falhas são registradas para não impedir a
 * concessão de XP, mas podem deixar a projeção defasada. A dívida está no
 * ADR-036.
 */
final readonly class SyncUserLevelOnLevelChanged
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: 'gamification.level.reached')]
    public function onLevelReached(LevelReached $event): void
    {
        $this->sync($event->userId(), $event->level());
    }

    #[AsEventListener(event: 'gamification.level.lost')]
    public function onLevelLost(LevelLost $event): void
    {
        $this->sync($event->userId(), $event->level());
    }

    private function sync(string $userId, int $level): void
    {
        try {
            $user = $this->userRepository->findById(UserId::fromString($userId));

            if (null === $user) {
                return;
            }

            $levelResult = UserLevel::fromInt($level);

            if ($levelResult->isInvalid()) {
                $this->logger->warning('Nível de gamificação inválido para projeção', [
                    'userId' => $userId,
                    'level' => $level,
                ]);

                return;
            }

            $user->syncLevel($levelResult->value());
            $this->userRepository->save($user);
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao projetar o nível de gamificação', [
                'userId' => $userId,
                'level' => $level,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
