<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Value Object imutável para data de nascimento.
 *
 * A data é civil (sem hora): o parser normaliza para meia-noite UTC apenas
 * como transporte. A idade é sempre derivada no fuso de São Paulo, porque é a
 * data civil brasileira que define a maioridade, e nunca é persistida.
 */
final readonly class BirthDate
{
    public const string STORAGE_FORMAT = 'Y-m-d';
    public const string AGE_TIMEZONE = 'America/Sao_Paulo';

    private const int MIN_YEAR = 1900;

    private function __construct(
        private DateTimeImmutable $value,
    ) {
    }

    /**
     * Cria uma data de nascimento a partir do formato `Y-m-d`.
     *
     * @return ValidationResult<self>
     */
    public static function fromString(
        string $date,
        string $field = 'birthDate',
        ?Instant $now = null,
    ): ValidationResult {
        $normalized = trim($date);

        if ('' === $normalized) {
            return ValidationResult::invalid([
                ValidationErrors::fieldRequired($field, 'A data de nascimento é obrigatória.'),
            ]);
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $normalized, new DateTimeZone('UTC'));
        $parseErrors = DateTimeImmutable::getLastErrors();

        if (false === $parsed
            || (false !== $parseErrors && (0 < $parseErrors['warning_count'] || 0 < $parseErrors['error_count']))
            || $parsed->format(self::STORAGE_FORMAT) !== $normalized
        ) {
            return ValidationResult::invalid([
                ValidationErrors::invalidFormat($field, 'Informe uma data válida no formato AAAA-MM-DD.'),
            ]);
        }

        if (self::MIN_YEAR > (int) $parsed->format('Y')) {
            return ValidationResult::invalid([
                ValidationErrors::numberOutOfRange(
                    $field,
                    'A data de nascimento deve ser posterior a '.self::MIN_YEAR.'.',
                ),
            ]);
        }

        $today = ($now ?? Instant::now())->inTimezone(new DateTimeZone(self::AGE_TIMEZONE));

        // Comparação por data civil: instantes absolutos em fusos diferentes
        // não representam o mesmo dia.
        if ($parsed->format(self::STORAGE_FORMAT) > $today->format(self::STORAGE_FORMAT)) {
            return ValidationResult::invalid([
                ValidationErrors::numberOutOfRange($field, 'A data de nascimento não pode estar no futuro.'),
            ]);
        }

        return ValidationResult::valid(new self($parsed));
    }

    /**
     * Calcula a idade em anos completos na data informada.
     *
     * Nascidos em 29/02 completam a idade em 01/03 nos anos não bissextos,
     * porque a data de aniversário não existe nesse ano.
     */
    public function ageAt(Instant $at): int
    {
        $today = $at->inTimezone(new DateTimeZone(self::AGE_TIMEZONE));

        $age = (int) $today->format('Y') - (int) $this->value->format('Y');

        $todayMonth = (int) $today->format('n');
        $todayDay = (int) $today->format('j');
        $birthMonth = (int) $this->value->format('n');
        $birthDay = (int) $this->value->format('j');

        if ($todayMonth < $birthMonth || ($todayMonth === $birthMonth && $todayDay < $birthDay)) {
            --$age;
        }

        return $age;
    }

    public function hasReached(int $minimumAge, Instant $at): bool
    {
        return $this->ageAt($at) >= $minimumAge;
    }

    public function value(): string
    {
        return $this->value->format(self::STORAGE_FORMAT);
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }

    public function __toString(): string
    {
        return $this->value();
    }
}
