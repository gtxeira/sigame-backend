<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\MessageHandler;

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Identity\Infrastructure\Mailer\Message\SendPasswordResetMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Entrega assíncrona do e-mail de redefinição de senha.
 */
#[AsMessageHandler]
final readonly class SendPasswordResetHandler
{
    public function __construct(
        private IdentityEmailMessageDecoder $decoder,
        private IdentityMailerInterface $mailer,
    ) {
    }

    public function __invoke(SendPasswordResetMessage $message): void
    {
        $decoded = $this->decoder->decode(
            encryptedCode: $message->encryptedCode,
            email: $message->email,
            expiresAtTimestamp: $message->expiresAtTimestamp,
            challengeId: $message->challengeId,
        );

        $this->mailer->sendPasswordReset(
            $decoded->to,
            $decoded->code,
            $decoded->expiresAt,
        );
    }
}
