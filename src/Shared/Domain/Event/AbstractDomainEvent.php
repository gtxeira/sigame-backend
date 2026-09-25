<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use App\Shared\Domain\ValueObject\Instant;

abstract readonly class AbstractDomainEvent implements DomainEventInterface
{
    protected function __construct(
        private Instant $occurredAt,
    ) {
    }

    final public function occurredAt(): Instant
    {
        return $this->occurredAt;
    }

    abstract public function eventName(): string;
}
