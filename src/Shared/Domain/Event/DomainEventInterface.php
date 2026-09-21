<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use App\Shared\Domain\ValueObject\DateTime;

/**
 * Interface para eventos de domínio.
 *
 * Todo evento de domínio deve implementar esta interface
 * para garantir consistência na dispatch de eventos.
 */
interface DomainEventInterface
{
    /**
     * Nome único do evento (ex: 'user.registered').
     */
    public function eventName(): string;

    /**
     * Data/hora em que o evento ocorreu.
     */
    public function occurredAt(): DateTime;

    /**
     * UUID do usuário que originou o evento (se aplicável).
     */
    public function userId(): ?string;
}
