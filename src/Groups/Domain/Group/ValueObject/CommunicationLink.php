<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
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

        if ('' === $normalized) {
            return ValidationResult::invalid([
                ValidationErrors::fieldRequired('communicationLink', 'O link de comunicação é obrigatório.'),
            ]);
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            return ValidationResult::invalid([
                ValidationErrors::stringTooLong(
                    'communicationLink',
                    sprintf('O link de comunicação deve ter no máximo %d caracteres.', self::MAX_LENGTH),
                ),
            ]);
        }

        if (false === filter_var($normalized, FILTER_VALIDATE_URL)) {
            return ValidationResult::invalid([
                ValidationErrors::invalidFormat('communicationLink', 'Informe uma URL http ou https.'),
            ]);
        }

        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return ValidationResult::invalid([
                ValidationErrors::invalidFormat('communicationLink', 'Informe uma URL http ou https.'),
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
