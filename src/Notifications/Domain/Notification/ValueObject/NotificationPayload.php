<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\ValueObject;

/**
 * Dados estruturados para deep link e renderização pelo cliente.
 *
 * Não inclui URLs de frontend, dados sensíveis nem notas internas.
 */
final readonly class NotificationPayload
{
    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $data
     */
    private function __construct(
        private array $data,
    ) {
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * @return array<string, scalar|array<array-key, scalar>|null>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
