<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\ValueObject;

use LogicException;

/**
 * Título curto exibido no sino e no e-mail.
 */
final readonly class NotificationTitle
{
    public const int MAX_LENGTH = 120;

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed || mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new LogicException('Título de notificação inválido.');
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
