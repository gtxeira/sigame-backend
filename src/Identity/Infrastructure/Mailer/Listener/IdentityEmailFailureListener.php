<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\Listener;

use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Infrastructure\Mailer\Message\SendEmailVerificationMessage;
use App\Identity\Infrastructure\Mailer\Message\SendPasswordResetMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Throwable;

/**
 * Compensa tokens quando a entrega do e-mail falha definitivamente.
 *
 * Para reset de senha, o token é consumido porque o usuário nunca recebeu o
 * link. Para verificação, o token continua válido para reenvio.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final readonly class IdentityEmailFailureListener
{
    public function __construct(
        private IdentityTokenRepositoryInterface $tokenRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();

        if ($message instanceof SendPasswordResetMessage) {
            $this->consumePasswordResetToken($message);

            return;
        }

        if ($message instanceof SendEmailVerificationMessage) {
            $this->logger->warning('Falha definitiva ao enviar verificação de e-mail', [
                'tokenHash' => $message->tokenHash,
            ]);
        }
    }

    private function consumePasswordResetToken(SendPasswordResetMessage $message): void
    {
        try {
            $token = $this->tokenRepository->findByHash($message->tokenHash);

            if (null !== $token && !$token->isConsumed()) {
                $this->tokenRepository->markConsumed($token);
            }

            $this->logger->warning('Falha definitiva ao enviar e-mail de reset de senha', [
                'tokenHash' => $message->tokenHash,
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao consumir token após erro de envio de e-mail', [
                'tokenHash' => $message->tokenHash,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
