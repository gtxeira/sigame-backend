<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\EmailVerification;

use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserEmailVerified;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;

/**
 * Consome o token e marca o e-mail canônico como verificado.
 */
final readonly class VerifyEmailHandler
{
    public function __construct(
        private IdentityTokenRepositoryInterface $tokenRepository,
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return Result<bool>
     */
    public function __invoke(string $rawToken): Result
    {
        $token = $this->tokenRepository->findByHash(hash('sha256', $rawToken));

        if (null === $token || !$token->matchesPurpose(IdentityTokenPurpose::EmailVerification)) {
            return Result::failure([IdentityErrors::emailVerificationTokenInvalid()]);
        }

        if ($token->isConsumed()) {
            return Result::failure([IdentityErrors::emailVerificationTokenInvalid()]);
        }

        if ($token->isExpiredAt(Instant::fromDateTime($this->clock->now()))) {
            return Result::failure([IdentityErrors::emailVerificationTokenExpired()]);
        }

        if (!$this->tokenRepository->markConsumed($token)) {
            return Result::failure([IdentityErrors::emailVerificationTokenInvalid()]);
        }

        /** @var User $user */
        $user = $token->user();
        $user->verifyEmail(Instant::fromDateTime($this->clock->now()));
        $this->userRepository->save($user);

        $this->eventDispatcher->dispatch(UserEmailVerified::now(
            userId: $user->id()->value(),
            email: $user->email()->value(),
        ));

        return Result::success(true);
    }
}
