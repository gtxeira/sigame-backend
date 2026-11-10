<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Event;

use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Identity\Domain\User\Event\UserRegistered;
use App\Shared\Domain\Exception\InvalidUuidException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Cria o progresso inicial no cadastro.
 */
#[AsEventListener(event: 'user.registered')]
final readonly class CreateProgressOnUserRegistered
{
    public function __construct(
        private UserProgressRepositoryInterface $progressRepository,
    ) {
    }

    public function __invoke(UserRegistered $event): void
    {
        try {
            $userId = PlayerId::fromString($event->userId());
        } catch (InvalidUuidException) {
            return;
        }

        if (null !== $this->progressRepository->findByUserId($userId)) {
            return;
        }

        try {
            $this->progressRepository->save(UserProgress::create($userId));
        } catch (UniqueConstraintViolationException) {
            // Corrida com outro listener/request: o progresso já existe.
        }
    }
}
