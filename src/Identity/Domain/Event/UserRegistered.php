<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\ValueObject\DateTime;

/**
 * Evento disparado quando um novo usuário se registra com sucesso.
 */
final readonly class UserRegistered implements DomainEventInterface
{
    public function __construct(
        private string $userId,
        private string $email,
        private string $cpf,
    ) {
    }

    public function eventName(): string
    {
        return 'user.registered';
    }

    public function occurredAt(): DateTime
    {
        return DateTime::now();
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
