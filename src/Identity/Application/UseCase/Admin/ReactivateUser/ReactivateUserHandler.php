<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\ReactivateUser;

use App\Identity\Application\UseCase\Admin\AccountStatusChangeResult;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\UserSuspension;
use App\Identity\Domain\User\Event\UserReactivated;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSuspensionRepositoryInterface;
use App\Identity\Domain\User\ValueObject\SuspensionAction;
use App\Identity\Domain\User\ValueObject\SuspensionReason;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Reativa uma conta suspensa e registra o histórico.
 */
final readonly class ReactivateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserSuspensionRepositoryInterface $suspensionRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<AccountStatusChangeResult>
     */
    public function __invoke(ReactivateUserCommand $command): Result
    {
        return $this->userRepository->transactional(function () use ($command): Result {
            $user = $this->userRepository->findById($command->userId);

            if (null === $user) {
                return Result::failure([IdentityErrors::userNotFound()]);
            }

            $reason = null;

            if (null !== $command->reason && '' !== trim($command->reason)) {
                $reasonResult = SuspensionReason::fromString($command->reason);

                if ($reasonResult->isFailure()) {
                    return Result::failure($reasonResult->errors());
                }

                $reason = $reasonResult->value();
            }

            if (!$user->reactivate()) {
                return Result::success(new AccountStatusChangeResult($user, null, false));
            }

            $actor = $this->userRepository->findById($command->actorId);

            if (null === $actor) {
                return Result::failure([IdentityErrors::userNotFound()]);
            }

            $this->userRepository->save($user);

            $suspension = UserSuspension::record(
                user: $user,
                actor: $actor,
                action: SuspensionAction::REACTIVATED,
                reason: $reason?->value(),
                occurredAt: Instant::now(),
            );

            $this->suspensionRepository->save($suspension);

            $this->eventDispatcher->dispatch(UserReactivated::now(
                userId: $user->id()->value(),
                suspensionId: $suspension->id()->value(),
                actorId: $actor->id()->value(),
                reason: $reason?->value(),
            ));

            return Result::success(new AccountStatusChangeResult($user, $suspension, true));
        });
    }
}
