<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\ValueObject;

use LogicException;

/**
 * Corpo curto exibido no sino e no e-mail.
 */
final readonly class NotificationBody
{
    public const int MAX_LENGTH = 500;

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed || mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new LogicException('Corpo de notificação inválido.');
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
