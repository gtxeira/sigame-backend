<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Limite máximo de membros aprovados de um grupo.
 *
 * A ausência do objeto (`null` no agregado) representa grupo ilimitado.
 */
final readonly class GroupMemberLimit
{
    private function __construct(
        private int $value,
    ) {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromInt(int $value): ValidationResult
    {
        if ($value < GroupPolicy::MIN_CONFIGURABLE_MEMBERS) {
            return ValidationResult::invalid([
                ValidationErrors::numberOutOfRange(
                    'maxMembers',
                    sprintf('O limite de membros deve ser no mínimo %d.', GroupPolicy::MIN_CONFIGURABLE_MEMBERS),
                ),
            ]);
        }

        return ValidationResult::valid(new self($value));
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
