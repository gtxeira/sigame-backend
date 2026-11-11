<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Nome exibido no perfil do usuário.
 */
final readonly class ProfileName
{
    public const int MIN_LENGTH = 2;
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
        $normalized = trim($value);
        $length = mb_strlen($normalized);

        if ('' === $normalized) {
            return ValidationResult::invalid([
                ValidationErrors::fieldRequired('name', 'O nome é obrigatório.'),
            ]);
        }

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooShort(
                    'name',
                    sprintf('O nome deve ter entre %d e %d caracteres.', self::MIN_LENGTH, self::MAX_LENGTH),
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
