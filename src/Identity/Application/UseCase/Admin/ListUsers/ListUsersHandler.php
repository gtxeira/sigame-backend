<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\ListUsers;

use App\Identity\Domain\User\Repository\UserPage;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista usuários para a administração global.
 */
final readonly class ListUsersHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return Result<UserPage>
     */
    public function __invoke(ListUsersQuery $query): Result
    {
        return Result::success($this->userRepository->search($query->criteria));
    }
}
