<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\ListStatusHistory;

use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSuspensionRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista o histórico append-only de suspensões e reativações.
 */
final readonly class ListStatusHistoryHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserSuspensionRepositoryInterface $suspensionRepository,
    ) {
    }

    /**
     * @return Result<UserSuspensionPage>
     */
    public function __invoke(ListStatusHistoryQuery $query): Result
    {
        if (null === $this->userRepository->findById($query->userId)) {
            return Result::failure([IdentityErrors::userNotFound()]);
        }

        $perPage = min(30, max(1, $query->perPage));
        $page = max(1, $query->page);

        return Result::success(new UserSuspensionPage(
            items: $this->suspensionRepository->findByUser(
                $query->userId,
                $perPage,
                ($page - 1) * $perPage,
            ),
            total: $this->suspensionRepository->countByUser($query->userId),
        ));
    }
}
