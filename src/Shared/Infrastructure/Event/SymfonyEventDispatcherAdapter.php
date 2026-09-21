<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Application\Event\EventDispatcherInterface as ApplicationEventDispatcherInterface;
use App\Shared\Domain\Event\DomainEventInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Adapter que conecta o Domain Event Dispatcher ao Symfony EventDispatcher.
 *
 * Converte DomainEventInterface em eventos Symfony para dispatch.
 */
final readonly class SymfonyEventDispatcherAdapter implements ApplicationEventDispatcherInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function dispatch(DomainEventInterface $event): void
    {
        $this->dispatcher->dispatch($event, $event->eventName());
    }
}
