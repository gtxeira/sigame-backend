<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Password;

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Troca a senha de uma conta autenticada e revoga todas as sessões.
 */
final readonly class ChangePasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private TokenPairIssuerInterface $tokenPairIssuer,
    ) {
    }

    /**
     * @return Result<bool>
     */
    public function __invoke(User $user, string $currentPassword, string $newPassword): Result
    {
        $current = $user->password();

        if (null === $current || !$this->passwordHasher->verify($currentPassword, $current)) {
            return Result::failure([IdentityErrors::passwordCurrentInvalid()]);
        }

        $user->changePassword($this->passwordHasher->hash($newPassword));
        $this->userRepository->save($user);
        $this->tokenPairIssuer->revokeAllSessions($user->id());

        return Result::success(true);
    }
}
