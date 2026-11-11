<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\Result;

/**
 * Motivo de suspensão/reativação informado pela administração.
 *
 * O motivo é público ao titular da conta; não use anotações internas.
 */
final readonly class SuspensionReason
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 500;

    private function __construct(
        private string $value,
    ) {
    }

    /**
     * @return Result<self>
     */
    public static function fromString(string $value): Result
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ('' === $trimmed) {
            return Result::failure([
                ValidationErrors::fieldRequired('reason', 'O motivo é obrigatório.'),
            ]);
        }

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return Result::failure([
                ValidationErrors::stringTooShort(
                    'reason',
                    sprintf('O motivo deve ter entre %d e %d caracteres.', self::MIN_LENGTH, self::MAX_LENGTH),
                ),
            ]);
        }

        return Result::success(new self($trimmed));
    }

    public function value(): string
    {
        return $this->value;
    }
}
