<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\CompleteOnboarding;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\OnboardingCompleted;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use RuntimeException;

/**
 * Conclui o onboarding do usuário autenticado.
 *
 * RN03.2: ao concluir os dados mínimos, o usuário alcança o nível 2.
 * As violações de regra de negócio são devolvidas pelo agregado como `Result`
 * e propagadas sem efeitos colaterais.
 */
final readonly class CompleteOnboardingHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<User>
     */
    public function __invoke(CompleteOnboardingCommand $command): Result
    {
        $user = $this->userRepository->findById($command->userId());

        if (null === $user) {
            throw new RuntimeException('Usuário autenticado não encontrado.');
        }

        $onboarding = $user->completeOnboarding(
            modalities: $command->modalities(),
            primaryModality: $command->primaryModality(),
        );

        if ($onboarding->isFailure()) {
            return Result::failure($onboarding->errors());
        }

        $this->userRepository->save($user);

        $this->eventDispatcher->dispatch(OnboardingCompleted::now(
            userId: $user->id()->value(),
        ));

        return Result::success($user);
    }
}
