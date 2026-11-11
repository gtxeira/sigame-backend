<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;

final readonly class ActivityDescription
{
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
                ValidationErrors::fieldRequired('description', 'A descrição não pode ser vazia.'),
            ]);
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooLong(
                    'description',
                    sprintf('A descrição da atividade deve ter no máximo %d caracteres.', self::MAX_LENGTH),
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
