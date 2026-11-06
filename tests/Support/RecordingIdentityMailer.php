<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use DateTimeZone;

/**
 * Mailer de teste que guarda os tokens em memória.
 */
final class RecordingIdentityMailer implements IdentityMailerInterface
{
    /**
     * @var list<array{type: string, email: string, token: string, expiresAt: Instant}>
     */
    private array $messages = [];

    public function sendEmailVerification(Email $to, string $token, Instant $expiresAt): void
    {
        $this->messages[] = [
            'type' => 'verification',
            'email' => $to->value(),
            'token' => $token,
            'expiresAt' => $this->formatInstant($expiresAt),
        ];
    }

    public function sendPasswordReset(Email $to, string $token, Instant $expiresAt): void
    {
        $this->messages[] = [
            'type' => 'password_reset',
            'email' => $to->value(),
            'token' => $token,
            'expiresAt' => $this->formatInstant($expiresAt),
        ];
    }

    public function lastToken(string $type, string $email): ?string
    {
        $token = null;

        foreach ($this->messages as $message) {
            if ($message['type'] === $type && $message['email'] === $email) {
                $token = $message['token'];
            }
        }

        return $token;
    }

    public function count(string $type, string $email): int
    {
        $count = 0;

        foreach ($this->messages as $message) {
            if ($message['type'] === $type && $message['email'] === $email) {
                ++$count;
            }
        }

        return $count;
    }

    public function clear(): void
    {
        $this->messages = [];
    }

    private function formatInstant(Instant $expiresAt): string
    {
        return $expiresAt->formatInTimezone(new DateTimeZone('America/Sao_Paulo'), 'd/m/y H:i');
    }
}
