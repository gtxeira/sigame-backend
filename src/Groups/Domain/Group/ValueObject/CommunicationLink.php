<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

use App\Groups\Domain\Error\GroupErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Link externo de comunicação do grupo (RF08).
 *
 * Não há chat próprio na V1; o grupo aponta para uma ferramenta externa.
 */
final readonly class CommunicationLink
{
    public const int MAX_LENGTH = 2048;

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
                GroupErrors::communicationLinkInvalid(),
            ]);
        }

        if (false === filter_var($normalized, FILTER_VALIDATE_URL)) {
            return ValidationResult::invalid([
                GroupErrors::communicationLinkInvalid(),
            ]);
        }

        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return ValidationResult::invalid([
                GroupErrors::communicationLinkInvalid(),
            ]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
