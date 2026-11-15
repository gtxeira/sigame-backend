<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\MessageHandler;

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Identity\Infrastructure\Mailer\Message\SendAccountExistsMessage;
use App\Shared\Domain\ValueObject\Email;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Entrega assíncrona do aviso de conta existente.
 */
#[AsMessageHandler]
final readonly class SendAccountExistsHandler
{
    public function __construct(
        private IdentityMailerInterface $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendAccountExistsMessage $message): void
    {
        $emailResult = Email::fromString($message->email);

        if ($emailResult->isInvalid()) {
            $this->logger->error('Aviso de conta existente com e-mail inválido', [
                'email' => $message->email,
            ]);

            throw new UnrecoverableMessageHandlingException('Payload de aviso inválido.');
        }

        $this->mailer->sendAccountExists($emailResult->value());
    }
}
