<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;

final readonly class ActivityTitle
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 100;

    private function __construct(private string $value)
    {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromString(string $value): ValidationResult
    {
        $normalized = trim($value);
        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooShort(
                    'title',
                    sprintf('O título da atividade deve ter entre %d e %d caracteres.', self::MIN_LENGTH, self::MAX_LENGTH),
                ),
            ]);
        }

        if ($length > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooLong(
                    'title',
                    sprintf('O título da atividade deve ter entre %d e %d caracteres.', self::MIN_LENGTH, self::MAX_LENGTH),
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
