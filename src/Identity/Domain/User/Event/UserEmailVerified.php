<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando o e-mail canônico do usuário é verificado.
 */
final readonly class UserEmailVerified extends AbstractDomainEvent
{
    private function __construct(
        private string $userId,
        private string $email,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $userId, string $email): self
    {
        return new self(
            userId: $userId,
            email: $email,
            occurredAt: Instant::now(),
        );
    }

    public function eventName(): string
    {
        return 'user.email_verified';
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function email(): string
    {
        return $this->email;
    }
}
