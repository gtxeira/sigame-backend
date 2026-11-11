<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\AssignBirthDate;

use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserDeleted;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\Policy\AgePolicy;
use App\Shared\Domain\ValueObject\BirthDate;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * Define a data de nascimento de um usuário autenticado.
 *
 * A data é write-once e a idade é sempre derivada no momento da definição.
 * Contas que informam idade abaixo do mínimo da plataforma e ainda não
 * concluíram o onboarding são removidas: não há base para reter os dados.
 */
final readonly class AssignBirthDateHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return Result<User>
     */
    public function __invoke(AssignBirthDateCommand $command): Result
    {
        $now = Instant::fromDateTime($this->clock->now());

        $birthDateResult = BirthDate::fromString($command->birthDate(), now: $now);

        if ($birthDateResult->isInvalid()) {
            return Result::failure($birthDateResult->errors());
        }

        $birthDate = $birthDateResult->value();
        $user = $this->userRepository->findById($command->userId());

        if (null === $user) {
            throw new RuntimeException('Usuário autenticado não encontrado.');
        }

        // Write-once: a idade só é avaliada na primeira definição, para nunca
        // excluir uma conta existente por causa de uma tentativa de alteração.
        if (null !== $user->birthDate()) {
            $assignment = $user->assignBirthDate($birthDate);

            if ($assignment->isFailure()) {
                return Result::failure($assignment->errors());
            }

            return Result::success($user);
        }

        if (!$birthDate->hasReached(AgePolicy::MINIMUM_AGE, $now)) {
            $this->blockUnderAgeAccount($user, $birthDate);

            return Result::failure([IdentityErrors::underMinimumAge(AgePolicy::MINIMUM_AGE)]);
        }

        $assignment = $user->assignBirthDate($birthDate);

        if ($assignment->isFailure()) {
            return Result::failure($assignment->errors());
        }

        $this->userRepository->save($user);

        return Result::success($user);
    }

    private function blockUnderAgeAccount(User $user, BirthDate $birthDate): void
    {
        // Conta sem dados: não há base para reter nada, então é removida.
        if ($user->isIncomplete()) {
            $this->removeAccount($user);

            return;
        }

        // Conta com histórico anterior à exigência da idade: bloqueia sem
        // apagar dados que o titular já produziu.
        $user->assignBirthDate($birthDate);
        $user->suspend();
        $this->userRepository->save($user);
    }

    private function removeAccount(User $user): void
    {
        $userId = $user->id()->value();

        $this->userRepository->transactional(function () use ($user, $userId): void {
            $this->userRepository->delete($user);
            $this->eventDispatcher->dispatch(UserDeleted::now($userId));
        });
    }
}
