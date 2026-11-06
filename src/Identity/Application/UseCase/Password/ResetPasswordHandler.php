<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Password;

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;

/**
 * Consome o token, grava a nova senha e revoga todas as sessões.
 */
final readonly class ResetPasswordHandler
{
    public function __construct(
        private IdentityTokenRepositoryInterface $tokenRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private TokenPairIssuerInterface $tokenPairIssuer,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return Result<bool>
     */
    public function __invoke(string $rawToken, string $newPassword): Result
    {
        $token = $this->tokenRepository->findByHash(hash('sha256', $rawToken));

        if (null === $token || !$token->matchesPurpose(IdentityTokenPurpose::PasswordReset)) {
            return Result::failure([IdentityErrors::passwordResetTokenInvalid()]);
        }

        if ($token->isConsumed()) {
            return Result::failure([IdentityErrors::passwordResetTokenInvalid()]);
        }

        if ($token->isExpiredAt(Instant::fromDateTime($this->clock->now()))) {
            return Result::failure([IdentityErrors::passwordResetTokenExpired()]);
        }

        if (!$this->tokenRepository->markConsumed($token)) {
            return Result::failure([IdentityErrors::passwordResetTokenInvalid()]);
        }

        /** @var User $user */
        $user = $token->user();

        if (!$user->isActive()) {
            return Result::failure([IdentityErrors::accountSuspended()]);
        }

        $user->setPassword($this->passwordHasher->hash($newPassword));
        $this->userRepository->save($user);
        $this->tokenPairIssuer->revokeAllSessions($user->id());

        return Result::success(true);
    }
}
