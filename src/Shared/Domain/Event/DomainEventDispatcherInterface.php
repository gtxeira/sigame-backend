<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Interface para dispatch de eventos de domínio.
 *
 * Implementada por adapters na camada Infrastructure.
 */
interface DomainEventDispatcherInterface
{
    public function dispatch(DomainEventInterface $event): void;
}
