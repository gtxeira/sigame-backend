<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\Listener;

use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpChallengeId;
use App\Identity\Infrastructure\Mailer\Message\SendEmailVerificationMessage;
use App\Identity\Infrastructure\Mailer\Message\SendPasswordResetMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Throwable;

/**
 * Compensa códigos quando a entrega do e-mail falha definitivamente.
 *
 * Para reset de senha, o código é consumido porque o usuário nunca recebeu a
 * mensagem. Para verificação, o código continua válido para reenvio.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final readonly class IdentityEmailFailureListener
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challengeRepository,
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
            $this->consumePasswordResetChallenge($message);

            return;
        }

        if ($message instanceof SendEmailVerificationMessage) {
            $this->logger->warning('Falha definitiva ao enviar verificação de e-mail', [
                'challengeId' => $message->challengeId,
            ]);
        }
    }

    private function consumePasswordResetChallenge(SendPasswordResetMessage $message): void
    {
        try {
            $challenge = $this->challengeRepository->findById(
                OtpChallengeId::fromString($message->challengeId),
            );

            if (null !== $challenge && !$challenge->isConsumed()) {
                $this->challengeRepository->markConsumed($challenge);
            }

            $this->logger->warning('Falha definitiva ao enviar e-mail de reset de senha', [
                'challengeId' => $message->challengeId,
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao consumir código após erro de envio de e-mail', [
                'challengeId' => $message->challengeId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
