<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\RemoveProfilePhoto;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\Functional\Result;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Throwable;

/**
 * Remove a foto de perfil do usuário autenticado.
 *
 * A operação é idempotente: sem foto cadastrada, não altera o usuário e
 * devolve sucesso. O objeto é apagado depois da persistência, em best-effort.
 */
final readonly class RemoveProfilePhotoHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ProfilePhotoStorageInterface $photoStorage,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return Result<User>
     */
    public function __invoke(UserId $userId): Result
    {
        $user = $this->userRepository->findById($userId);

        if (null === $user) {
            throw new RuntimeException('Usuário autenticado não encontrado.');
        }

        $lock = $this->lockFactory->createLock('profile-photo-'.$user->id()->value(), 30.0);

        if (!$lock->acquire(true)) {
            throw new RuntimeException('Não foi possível processar a remoção da foto de perfil.');
        }

        try {
            $previousKey = $user->photoKey();

            if (null === $previousKey) {
                return Result::success($user);
            }

            $user->changePhoto(null);
            $this->userRepository->save($user);

            try {
                $this->photoStorage->delete($previousKey);
            } catch (Throwable $exception) {
                $this->logger->warning('Falha ao remover o objeto da foto de perfil', [
                    'key' => $previousKey,
                    'reason' => $exception->getMessage(),
                ]);
            }

            return Result::success($user);
        } finally {
            $lock->release();
        }
    }
}
