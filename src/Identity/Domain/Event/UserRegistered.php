<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando um novo usuário se registra com sucesso.
 */
final readonly class UserRegistered extends AbstractDomainEvent
{
    private function __construct(
        private string $userId,
        private string $email,
        private string $cpf,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $userId, string $email, string $cpf): self
    {
        return new self(
            userId: $userId,
            email: $email,
            cpf: $cpf,
            occurredAt: Instant::now(),
        );
    }

    public function eventName(): string
    {
        return 'user.registered';
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function cpf(): string
    {
        return $this->cpf;
    }
}
