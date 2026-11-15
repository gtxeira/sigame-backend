<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Password;

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\PasswordReset\Repository\PasswordResetTicketRepositoryInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserEmailVerified;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\PlainPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;

/**
 * Consome o ticket, grava a nova senha e revoga todas as sessões.
 *
 * Como o reset só chega pelo e-mail canônico, concluir a troca também prova
 * a posse do endereço: um e-mail ainda não verificado é marcado como verificado.
 */
final readonly class ResetPasswordHandler
{
    public function __construct(
        private PasswordResetTicketRepositoryInterface $ticketRepository,
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
    public function __invoke(string $rawTicket, string $newPassword): Result
    {
        $passwordResult = PlainPassword::fromString($newPassword);

        if ($passwordResult->isInvalid()) {
            return Result::failure($passwordResult->errors());
        }

        $ticket = $this->ticketRepository->findByHash(hash('sha256', $rawTicket));

        if (null === $ticket || $ticket->isConsumed()) {
            return Result::failure([IdentityErrors::passwordResetTicketInvalid()]);
        }

        $now = Instant::fromDateTime($this->clock->now());

        if ($ticket->isExpiredAt($now)) {
            return Result::failure([IdentityErrors::passwordResetTicketExpired()]);
        }

        if (!$this->ticketRepository->markConsumed($ticket)) {
            return Result::failure([IdentityErrors::passwordResetTicketInvalid()]);
        }

        /** @var User $user */
        $user = $ticket->user();

        if (!$user->isActive()) {
            return Result::failure([IdentityErrors::accountSuspended()]);
        }

        $user->setPassword($this->passwordHasher->hash($newPassword));

        if (!$user->isEmailVerified()) {
            $user->verifyEmail($now);

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
