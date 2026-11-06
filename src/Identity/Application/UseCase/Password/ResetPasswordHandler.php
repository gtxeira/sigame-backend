<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Password;

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
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
 * Consome o token, grava a nova senha e revoga todas as sessões.
 *
 * Como o reset só chega pelo e-mail canônico, concluir a troca também prova
 * a posse do endereço: um e-mail ainda não verificado é marcado como verificado.
 */
final readonly class ResetPasswordHandler
{
    public function __construct(
        private IdentityTokenRepositoryInterface $tokenRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private TokenPairIssuerInterface $tokenPairIssuer,
        private EventDispatcherInterface $eventDispatcher,
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

        if (!$user->isEmailVerified()) {
            $user->verifyEmail(Instant::fromDateTime($this->clock->now()));

            $this->eventDispatcher->dispatch(UserEmailVerified::now(
                userId: $user->id()->value(),
                email: $user->email()->value(),
            ));
        }

        $this->userRepository->save($user);
        $this->tokenPairIssuer->revokeAllSessions($user->id());

        return Result::success(true);
    }
}
