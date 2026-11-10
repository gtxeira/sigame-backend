<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\SuspendUser;

use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Application\UseCase\Admin\AccountStatusChangeResult;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\UserSuspension;
use App\Identity\Domain\User\Event\UserSuspended;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSuspensionRepositoryInterface;
use App\Identity\Domain\User\ValueObject\SuspensionAction;
use App\Identity\Domain\User\ValueObject\SuspensionReason;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Suspende uma conta comum e registra o histórico append-only.
 */
final readonly class SuspendUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserSuspensionRepositoryInterface $suspensionRepository,
        private TokenPairIssuerInterface $tokenPairIssuer,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<AccountStatusChangeResult>
     */
    public function __invoke(SuspendUserCommand $command): Result
    {
        return $this->userRepository->transactional(function () use ($command): Result {
            $user = $this->userRepository->findById($command->userId);

            if (null === $user) {
                return Result::failure([IdentityErrors::userNotFound()]);
            }

            if (UserRole::ADMIN_SIGAME === $user->role()) {
                return Result::failure([IdentityErrors::cannotSuspendAdmin()]);
            }

            $reason = SuspensionReason::fromString($command->reason);

            if ($reason->isFailure()) {
                return Result::failure($reason->errors());
            }

            if (!$user->suspend()) {
                return Result::success(new AccountStatusChangeResult($user, null, false));
            }

            $actor = $this->userRepository->findById($command->actorId);

            if (null === $actor) {
                return Result::failure([IdentityErrors::userNotFound()]);
            }

            $this->userRepository->save($user);
            $this->tokenPairIssuer->revokeAllSessions($user->id());

            $suspension = UserSuspension::record(
                user: $user,
                actor: $actor,
                action: SuspensionAction::SUSPENDED,
                reason: $reason->value()->value(),
                occurredAt: Instant::now(),
            );

            $this->suspensionRepository->save($suspension);

            $this->eventDispatcher->dispatch(UserSuspended::now(
                userId: $user->id()->value(),
                suspensionId: $suspension->id()->value(),
                actorId: $actor->id()->value(),
                reason: $reason->value()->value(),
            ));

            return Result::success(new AccountStatusChangeResult($user, $suspension, true));
        });
    }
}
