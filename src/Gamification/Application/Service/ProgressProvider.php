<?php

declare(strict_types=1);

namespace App\Gamification\Application\Service;

use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;

/**
 * Obtém o progresso do usuário, criando-o de forma idempotente quando ainda
 * não existe (por exemplo, usuários anteriores à gamificação).
 */
final readonly class ProgressProvider
{
    public function __construct(
        private UserProgressRepositoryInterface $progressRepository,
    ) {
    }

    public function getOrCreate(PlayerId $userId): UserProgress
    {
        $progress = $this->progressRepository->findByUserId($userId);

        if (null !== $progress) {
            return $progress;
        }

        $progress = UserProgress::create($userId);
        $this->progressRepository->save($progress);

        return $progress;
    }
}
