<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Event;

use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Identity\Domain\User\Event\UserDeleted;
use App\Shared\Domain\Exception\InvalidUuidException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Remove a projeção de progresso quando a conta é apagada.
 *
 * A tabela `user_progress` não tem FK para `users` (o dado pertence a
 * Gamification), então a limpeza é feita aqui em vez de depender de cascata.
 */
#[AsEventListener(event: 'user.deleted')]
final readonly class DeleteProgressOnUserDeleted
{
    public function __construct(
        private UserProgressRepositoryInterface $progressRepository,
    ) {
    }

    public function __invoke(UserDeleted $event): void
    {
        try {
            $userId = PlayerId::fromString($event->userId());
        } catch (InvalidUuidException) {
            return;
        }

        $this->progressRepository->deleteByUserId($userId);
    }
}
