<?php

declare(strict_types=1);

namespace App\Identity\Domain\Otp\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;
use LogicException;

/**
 * Código OTP numérico de seis dígitos.
 *
 * O valor bruto nunca é persistido; o banco guarda apenas o hash.
 */
final readonly class OtpCode
{
    public const int LENGTH = 6;

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

        if (1 !== preg_match('/^\d{'.self::LENGTH.'}$/', $normalized)) {
            return ValidationResult::invalid([
                ValidationErrors::invalidFormat('code', 'Informe o código de 6 dígitos.'),
            ]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    /**
     * Usado por geradores confiáveis do próprio domínio.
     */
    public static function fromGenerated(string $value): self
    {
        if (1 !== preg_match('/^\d{'.self::LENGTH.'}$/', $value)) {
            throw new LogicException('O código OTP gerado deve ter 6 dígitos.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
