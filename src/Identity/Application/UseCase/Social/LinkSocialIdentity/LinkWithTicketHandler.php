<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Social\LinkSocialIdentity;

use App\Identity\Application\Service\SocialLinkTicketStoreInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;

/**
 * Vincula a identidade social já verificada a uma conta autenticada.
 */
final readonly class LinkWithTicketHandler
{
    public function __construct(
        private SocialLinkTicketStoreInterface $linkTicketStore,
        private UserRepositoryInterface $userRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return Result<bool>
     */
    public function __invoke(LinkWithTicketCommand $command): Result
    {
        $provider = IdentityProvider::tryFrom(strtolower($command->provider()));

        if (null === $provider || $provider->isLocal()) {
            return Result::failure([
                IdentityErrors::socialProviderUnsupported($command->provider()),
            ]);
        }

        $ticket = $this->linkTicketStore->consume($command->linkToken());

        if (null === $ticket || $ticket->isExpiredAt(Instant::fromDateTime($this->clock->now()))) {
            return Result::failure([IdentityErrors::socialLinkTokenInvalid()]);
        }

        if ($ticket->provider !== $provider) {
            return Result::failure([IdentityErrors::socialLinkTokenInvalid()]);
        }

        $user = $this->userRepository->findById($command->userId());

        if (null === $user) {
            return Result::failure([IdentityErrors::socialLinkTokenInvalid()]);
        }

        if (!$user->isActive()) {
            return Result::failure([IdentityErrors::accountSuspended()]);
        }

        // O ticket só nasce quando o e-mail do provedor coincide com uma conta
        // existente. Exigir a igualdade no vínculo impede usar o ticket em uma
        // conta diferente daquela que originou o 409.
        if (!$ticket->email->equals($user->email())) {
            return Result::failure([IdentityErrors::socialLinkEmailMismatch()]);
        }

        $existing = $this->userRepository->findByProviderSubject($provider, $ticket->subject);

        if (null !== $existing && !$existing->id()->equals($user->id())) {
            return Result::failure([IdentityErrors::socialIdentityAlreadyLinked()]);
        }

        if (null === $existing) {
            $user->linkSocialIdentity(
                provider: $ticket->provider,
                subject: $ticket->subject,
                email: $ticket->email,
                emailVerifiedAt: Instant::fromDateTime($this->clock->now()),
            );
            $this->userRepository->save($user);
        }

        return Result::success(true);
    }
}
