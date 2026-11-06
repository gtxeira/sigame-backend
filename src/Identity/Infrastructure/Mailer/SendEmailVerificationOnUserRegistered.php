<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer;

use App\Identity\Application\UseCase\EmailVerification\RequestEmailVerificationHandler;
use App\Identity\Domain\User\Event\UserRegistered;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;

/**
 * Dispara a verificação de e-mail depois de um cadastro local.
 *
 * Contas criadas por provedor já entram verificadas e o handler ignora.
 */
#[AsEventListener(event: 'user.registered')]
final readonly class SendEmailVerificationOnUserRegistered
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RequestEmailVerificationHandler $requestEmailVerificationHandler,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UserRegistered $event): void
    {
        try {
            $user = $this->userRepository->findById(UserId::fromString($event->userId()));

            if (null !== $user) {
                ($this->requestEmailVerificationHandler)($user);
            }
        } catch (Throwable $exception) {
            // O cadastro já foi persistido; falhas no envio não devem quebrá-lo.
            $this->logger->warning('Falha ao iniciar verificação de e-mail', [
                'userId' => $event->userId(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
