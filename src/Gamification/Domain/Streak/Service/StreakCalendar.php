<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Streak\Service;

use App\Gamification\Domain\Rule\GamificationRules;
use App\Shared\Domain\ValueObject\Instant;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Calendário local da gamificação.
 *
 * A semana vai de segunda a domingo e todas as datas são resolvidas no fuso
 * America/Sao_Paulo, independentemente do UTC armazenado.
 */
final readonly class StreakCalendar
{
    public function timezone(): DateTimeZone
    {
        return new DateTimeZone(GamificationRules::TIMEZONE);
    }

    public function localDate(Instant $instant): string
    {
        return $instant->formatInTimezone($this->timezone(), 'Y-m-d');
    }

    public function weekKey(Instant $instant): string
    {
        return $this->weekKeyForDate($this->localDate($instant));
    }

    public function weekKeyForDate(string $date): string
    {
        $value = new DateTimeImmutable($date.' 00:00:00', $this->timezone());

        return $value->modify('monday this week')->format('Y-m-d');
    }

    public function isWeekend(Instant $instant): bool
    {
        return (int) $instant->inTimezone($this->timezone())->format('N') >= 6;
    }

    public function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date.' 00:00:00', $this->timezone()))
            ->modify(sprintf('%+d days', $days))
            ->format('Y-m-d');
    }

    public function daysBetween(string $from, string $to): int
    {
        $start = new DateTimeImmutable($from.' 00:00:00', new DateTimeZone('UTC'));
        $end = new DateTimeImmutable($to.' 00:00:00', new DateTimeZone('UTC'));

        return (int) $start->diff($end)->format('%r%a');
    }
}
