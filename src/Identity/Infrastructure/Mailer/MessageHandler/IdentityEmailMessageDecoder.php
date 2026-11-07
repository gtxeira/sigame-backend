<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer\MessageHandler;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Crypto\EncryptedPayload;
use App\Shared\Infrastructure\Crypto\Exception\PayloadDecryptionFailed;
use App\Shared\Infrastructure\Crypto\PayloadEncryptor;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Decifra e valida o conteúdo comum das mensagens de e-mail de identidade.
 *
 * Falhas aqui são irrecuperáveis: não faz sentido tentar de novo um payload
 * corrompido ou cifrado com outra chave.
 */
final readonly class IdentityEmailMessageDecoder
{
    public function __construct(
        private PayloadEncryptor $encryptor,
        private LoggerInterface $logger,
    ) {
    }

    public function decode(
        string $encryptedToken,
        string $email,
        int $expiresAtTimestamp,
        string $tokenHash,
    ): DecodedIdentityEmail {
        try {
            $rawToken = $this->encryptor->decrypt(EncryptedPayload::fromBase64($encryptedToken));
        } catch (PayloadDecryptionFailed|InvalidArgumentException $exception) {
            $this->logger->error('Falha ao decifrar payload de e-mail de identidade', [
                'tokenHash' => $tokenHash,
                'reason' => $exception->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException(
                'Payload de e-mail inválido.',
                0,
                $exception,
            );
        }

        $emailResult = Email::fromString($email);
        $expiresAtResult = Instant::fromTimestamp($expiresAtTimestamp);

        if ($emailResult->isInvalid() || $expiresAtResult->isInvalid()) {
            $this->logger->error('Payload de e-mail de identidade com dados inválidos', [
                'tokenHash' => $tokenHash,
            ]);

            throw new UnrecoverableMessageHandlingException('Payload de e-mail inválido.');
        }

        return new DecodedIdentityEmail(
            to: $emailResult->value(),
            rawToken: $rawToken,
            expiresAt: $expiresAtResult->value(),
        );
    }
}
