<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;
use DateInterval;
use DateInvalidOperationException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Value Object imutável para datetimes, normalizado em UTC.
 *
 * Envelopa DateTimeImmutable para manter consistência no domínio.
 */
final readonly class Instant
{
    private function __construct(
        private DateTimeImmutable $value,
    ) {
    }

    public static function now(): self
    {
        return new self(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromString(string $datetime, string $field = 'datetime'): ValidationResult
    {
        $formats = [
            DateTimeInterface::RFC3339_EXTENDED,
            DateTimeInterface::RFC3339,
        ];

        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat(
                $format,
                $datetime,
            );

            if ($dt !== false) {
                return ValidationResult::valid(
                    new self(
                        $dt->setTimezone(new DateTimeZone('UTC')),
                    ),
                );
            }
        }

        return ValidationResult::invalid([
            ValidationErrors::invalidFormat($field, 'Informe uma data RFC 3339 (com ou sem milissegundos) com timezone.'),
        ]);
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromTimestamp(int $timestamp): ValidationResult
    {
        try {
            $dateTime = new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            )->setTimestamp($timestamp);

            return ValidationResult::valid(
                new self($dateTime),
            );
        } catch (Throwable) {
            return ValidationResult::invalid([
                ValidationErrors::invalidFormat('timestamp', 'O timestamp informado é inválido.'),
            ]);
        }
    }

    public static function fromDateTime(
        DateTimeInterface $value,
    ): self {
        return new self(
            DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone('UTC')),
        );
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function timestamp(): int
    {
        return $this->value->getTimestamp();
    }

    /**
     * @return ValidationResult<self>
     */
    public function addDays(int $days): ValidationResult
    {
        if ($days <= 0) {
            return $this->invalidAmount('days', 'adicionar');
        }

        return $this->shift(
            new DateInterval("P{$days}D"),
            subtract: false,
            unit: 'days',
        );
    }

    /**
     * @return ValidationResult<self>
     */
    public function subDays(int $days): ValidationResult
    {
        if ($days <= 0) {
            return $this->invalidAmount('days', 'subtrair');
        }

        return $this->shift(
            new DateInterval("P{$days}D"),
            subtract: true,
            unit: 'days',
        );
    }

    /**
     * @return ValidationResult<self>
     */
    public function addHours(int $hours): ValidationResult
    {
        if ($hours <= 0) {
            return $this->invalidAmount('hours', 'adicionar');
        }

        return $this->shift(
            new DateInterval("PT{$hours}H"),
            subtract: false,
            unit: 'hours',
        );
    }

    /**
     * @return ValidationResult<self>
     */
    public function subHours(int $hours): ValidationResult
    {
        if ($hours <= 0) {
            return $this->invalidAmount('hours', 'subtrair');
        }

        return $this->shift(
            new DateInterval("PT{$hours}H"),
            subtract: true,
            unit: 'hours',
        );
    }

    /**
     * @return ValidationResult<self>
     */
    public function addMinutes(int $minutes): ValidationResult
    {
        if ($minutes <= 0) {
            return $this->invalidAmount('minutes', 'adicionar');
        }

        return $this->shift(
            new DateInterval("PT{$minutes}M"),
            subtract: false,
            unit: 'minutes',
        );
    }

    /**
     * @return ValidationResult<self>
     */
    public function subMinutes(int $minutes): ValidationResult
    {
        if ($minutes <= 0) {
            return $this->invalidAmount('minutes', 'subtrair');
        }

        return $this->shift(
            new DateInterval("PT{$minutes}M"),
            subtract: true,
            unit: 'minutes',
        );
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value->format('U.u')
            === $other->value->format('U.u');
    }

    public function format(string $format = DateTimeInterface::RFC3339_EXTENDED): string
    {
        return $this->value->format($format);
    }

    /**
     * Retorna a data e hora no formato ISO 8601 com milissegundos e timezone, em UTC.
     *
     * Exemplo: `2023-07-10T14:30:00.123+00:00`
     */
    public function toIsoStringWithMilliseconds(): string
    {
        return $this->value->format(DateTimeInterface::RFC3339_EXTENDED);
    }

    /**
     * Retorna a data e hora no formato ISO 8601 com microssegundos e timezone, em UTC.
     *
     * Exemplo: `2023-07-10T14:30:00.123456+00:00`
     */
    public function toIsoStringWithMicroseconds(): string
    {
        return $this->value->format('Y-m-d\TH:i:s.uP');
    }

    /**
     * Retorna o DateTimeImmutable alterado para o fuso horário especificado.
     */
    public function inTimezone(DateTimeZone $timezone): DateTimeImmutable
    {
        return $this->value->setTimezone($timezone);
    }

    /**
     * Formata o Instant no fuso horário especificado.
     */
    public function formatInTimezone(
        DateTimeZone $timezone,
        string $format = DateTimeInterface::RFC3339_EXTENDED,
    ): string {
        return $this->inTimezone($timezone)->format($format);
    }

    public function __toString(): string
    {
        return $this->toIsoStringWithMicroseconds();
    }

    /**
     * @return ValidationResult<self>
     */
    private function invalidAmount(
        string $field,
        string $operation,
    ): ValidationResult {
        return ValidationResult::invalid([
            ValidationErrors::numberOutOfRange($field, sprintf('Informe um valor positivo maior que zero para %s %s.', $operation, $field)),
        ]);
    }

    /**
     * @return ValidationResult<self>
     */
    private function shift(
        DateInterval $interval,
        bool $subtract,
        string $unit,
    ): ValidationResult {
        try {
            $value = $subtract
                ? $this->value->sub($interval)
                : $this->value->add($interval);

            return ValidationResult::valid(
                new self($value),
            );
        } catch (DateInvalidOperationException) {
            return ValidationResult::invalid([
                ValidationErrors::invalidFormat($unit, sprintf('Não foi possível modificar o datetime em %s.', $unit)),
            ]);
        }
    }
}
