<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Motivo textual de cancelamento, remarcação ou invalidação.
 */
final readonly class ActivityReason
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 500;

    private function __construct(private string $value)
    {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromString(string $value): ValidationResult
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            return ValidationResult::invalid([
                ValidationErrors::fieldRequired('reason', 'O motivo é obrigatório.'),
            ]);
        }

        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooShort(
                    'reason',
                    sprintf('O motivo deve ter entre %d e %d caracteres.', self::MIN_LENGTH, self::MAX_LENGTH),
                ),
            ]);
        }

        if ($length > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooLong(
                    'reason',
                    sprintf('O motivo deve ter no máximo %d caracteres.', self::MAX_LENGTH),
                ),
            ]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    public function value(): string
    {
        return $this->value;
    }
}
