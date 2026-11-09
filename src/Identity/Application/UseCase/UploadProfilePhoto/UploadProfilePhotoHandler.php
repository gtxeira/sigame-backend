<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\UploadProfilePhoto;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\Functional\Result;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Throwable;

/**
 * Grava a foto no storage e troca a chave persistida no usuário.
 *
 * O lock por usuário serializa uploads concorrentes. Se a persistência falhar,
 * o objeto recém-gravado é removido para não deixar órfãos; a remoção do
 * objeto antigo é best-effort e nunca desfaz o upload bem-sucedido.
 */
final readonly class UploadProfilePhotoHandler
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
    public function __invoke(UploadProfilePhotoCommand $command): Result
    {
        $user = $this->userRepository->findById($command->userId());

        if (null === $user) {
            throw new RuntimeException('Usuário autenticado não encontrado.');
        }

        $lock = $this->lockFactory->createLock('profile-photo-'.$user->id()->value(), 30.0);

        if (!$lock->acquire(true)) {
            throw new RuntimeException('Não foi possível processar o upload da foto de perfil.');
        }

        try {
            $previousKey = $user->photoKey();
            $newKey = $this->photoStorage->store(
                userId: $user->id(),
                sourcePath: $command->sourcePath(),
                mimeType: $command->mimeType(),
            );

            try {
                $user->changePhoto($newKey);
                $this->userRepository->save($user);
            } catch (Throwable $exception) {
                $this->photoStorage->delete($newKey);

                throw $exception;
            }

            if (null !== $previousKey && $previousKey !== $newKey) {
                $this->deleteBestEffort($previousKey);
            }

            return Result::success($user);
        } finally {
            $lock->release();
        }
    }

    private function deleteBestEffort(string $key): void
    {
        try {
            $this->photoStorage->delete($key);
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao remover a foto de perfil anterior', [
                'key' => $key,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
