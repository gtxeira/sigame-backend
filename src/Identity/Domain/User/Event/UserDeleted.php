<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando uma conta é removida.
 *
 * Ocorre quando a data de nascimento informada indica idade abaixo do mínimo
 * exigido pela plataforma. Módulos que guardam dados por usuário devem apagar
 * o que mantêm.
 */
final readonly class UserDeleted extends AbstractDomainEvent
{
    private function __construct(
        private string $userId,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $userId): self
    {
        return new self(
            userId: $userId,
            occurredAt: Instant::now(),
        );
    }

    public function eventName(): string
    {
        return 'user.deleted';
    }

    public function userId(): string
    {
        return $this->userId;
    }
}
