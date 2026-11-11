<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
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

        if ('' === $normalized) {
            return ValidationResult::invalid([
                ValidationErrors::fieldRequired('reason', 'O motivo da remoção é obrigatório.'),
            ]);
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooLong(
                    'reason',
                    sprintf('O motivo da remoção deve ter no máximo %d caracteres.', self::MAX_LENGTH),
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
