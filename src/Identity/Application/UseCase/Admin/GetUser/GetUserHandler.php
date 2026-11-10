<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\GetUser;

use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Carrega uma conta para a administração global.
 */
final readonly class GetUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return Result<User>
     */
    public function __invoke(GetUserQuery $query): Result
    {
        $user = $this->userRepository->findById($query->userId);

        if (null === $user) {
            return Result::failure([IdentityErrors::userNotFound()]);
        }

        return Result::success($user);
    }
}
