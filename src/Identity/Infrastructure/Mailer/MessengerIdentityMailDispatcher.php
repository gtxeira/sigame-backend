<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer;

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Infrastructure\Mailer\Message\SendEmailVerificationMessage;
use App\Identity\Infrastructure\Mailer\Message\SendPasswordResetMessage;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Crypto\PayloadEncryptor;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adaptador da porta de enfileiramento para o Messenger.
 *
 * Cifra o token bruto antes de publicar e mantém a mensagem livre de entidades
 * de domínio.
 */
final readonly class MessengerIdentityMailDispatcher implements IdentityMailDispatcherInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private PayloadEncryptor $encryptor,
    ) {
    }

    public function dispatchEmailVerification(
        Email $to,
        string $rawToken,
        Instant $expiresAt,
        string $tokenHash,
    ): void {
        $this->bus->dispatch(new SendEmailVerificationMessage(
            email: $to->value(),
            encryptedToken: $this->encryptor->encrypt($rawToken)->toBase64(),
            expiresAtTimestamp: $expiresAt->timestamp(),
            tokenHash: $tokenHash,
        ));
    }

    public function dispatchPasswordReset(
        Email $to,
        string $rawToken,
        Instant $expiresAt,
        string $tokenHash,
    ): void {
        $this->bus->dispatch(new SendPasswordResetMessage(
            email: $to->value(),
            encryptedToken: $this->encryptor->encrypt($rawToken)->toBase64(),
            expiresAtTimestamp: $expiresAt->timestamp(),
            tokenHash: $tokenHash,
        ));
    }
}
