<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Identity\Infrastructure\Mailer\MailDateFormatter;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Mailer de teste que guarda os códigos em memória.
 */
final class RecordingIdentityMailer implements IdentityMailerInterface
{
    /**
     * @var list<array{type: string, email: string, code: ?string, expiresAt: ?string}>
     */
    private array $messages = [];

    public function sendEmailVerification(Email $to, string $code, Instant $expiresAt): void
    {
        $this->messages[] = [
            'type' => 'verification',
            'email' => $to->value(),
            'code' => $code,
            'expiresAt' => MailDateFormatter::format($expiresAt),
        ];
    }

    public function sendPasswordReset(Email $to, string $code, Instant $expiresAt): void
    {
        $this->messages[] = [
            'type' => 'password_reset',
            'email' => $to->value(),
            'code' => $code,
            'expiresAt' => MailDateFormatter::format($expiresAt),
        ];
    }

    public function sendAccountExists(Email $to): void
    {
        $this->messages[] = [
            'type' => 'account_exists',
            'email' => $to->value(),
            'code' => null,
            'expiresAt' => null,
        ];
    }

    public function lastCode(string $type, string $email): ?string
    {
        $code = null;

        foreach ($this->messages as $message) {
            if ($message['type'] === $type && $message['email'] === $email) {
                $code = $message['code'];
            }
        }

        return $code;
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

    public function lastExpiresAt(string $type, string $email): ?string
    {
        $expiresAt = null;

        foreach ($this->messages as $message) {
            if ($message['type'] === $type && $message['email'] === $email) {
                $expiresAt = $message['expiresAt'];
            }
        }

        return $expiresAt;
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
