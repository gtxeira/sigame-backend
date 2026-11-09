<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

use App\Groups\Domain\Error\GroupErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Motivo registrado na remoção de um membro (RN06.3).
 */
final readonly class RemovalReason
{
    public const int MAX_LENGTH = 500;

    private function __construct(
        private string $value,
    ) {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromString(string $value): ValidationResult
    {
        $normalized = trim($value);

        if ('' === $normalized || mb_strlen($normalized) > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                GroupErrors::removalReasonRequired(self::MAX_LENGTH),
            ]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    public function value(): string
    {
        return $this->value;
    }
}
