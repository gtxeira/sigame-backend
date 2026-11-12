<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\UpdateModalities;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\Functional\Result;
use RuntimeException;

/**
 * Substitui as modalidades do usuário autenticado.
 *
 * Não dispara `OnboardingCompleted`: a recompensa de 100 XP e a mudança para o
 * nível 2 são exclusivas da primeira conclusão do onboarding.
 */
final readonly class UpdateModalitiesHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return Result<User>
     */
    public function __invoke(UpdateModalitiesCommand $command): Result
    {
        $user = $this->userRepository->findById($command->userId());

        if (null === $user) {
            throw new RuntimeException('Usuário autenticado não encontrado.');
        }

        $result = $user->replaceModalities(
            modalities: $command->modalities(),
            primaryModality: $command->primaryModality(),
        );

        if ($result->isFailure()) {
            return Result::failure($result->errors());
        }

        // Primeira gravação: modalidades substituídas, todas sem principal.
        $this->userRepository->save($user);

        // Segunda gravação: marca só a principal, sem risco de colidir com o
        // índice único parcial que a gravação anterior acabou de limpar.
        if (null !== $command->primaryModality()) {
            $user->setPrimaryModality($command->primaryModality());
            $this->userRepository->save($user);
        }

        return Result::success($user);
    }
}
