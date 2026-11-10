<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\ValueObject;

use LogicException;

/**
 * Chave idempotente de uma notificação por destinatário.
 */
final readonly class DeduplicationKey
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed || mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new LogicException('Chave de idempotência inválida.');
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
