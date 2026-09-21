<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEventInterface;

/**
 * Interface para dispatch de eventos na camada de Application.
 *
 * Esta é a porta que os handlers de use cases usam para disparar eventos.
 */
interface EventDispatcherInterface
{
    public function dispatch(DomainEventInterface $event): void;
}
