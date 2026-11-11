<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Senha em texto puro, validada antes do hash.
 */
final readonly class PlainPassword
{
    public const int MIN_LENGTH = 8;
    public const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromString(string $value): ValidationResult
    {
        $length = mb_strlen($value);

        if (0 === $length) {
            return ValidationResult::invalid([
                ValidationErrors::fieldRequired('password', 'A senha é obrigatória.'),
            ]);
        }

        if ($length < self::MIN_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooShort(
                    'password',
                    sprintf('A senha deve ter no mínimo %d caracteres.', self::MIN_LENGTH),
                ),
            ]);
        }

        if ($length > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooLong(
                    'password',
                    sprintf('A senha deve ter no máximo %d caracteres.', self::MAX_LENGTH),
                ),
            ]);
        }

        return ValidationResult::valid(new self($value));
    }

    public function value(): string
    {
        return $this->value;
    }
}
