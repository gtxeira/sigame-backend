<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Policy;

use App\Shared\Domain\ValueObject\Instant;

/**
 * Regras quantitativas e janelas operacionais das atividades.
 */
final class ActivityPolicy
{
    public const int MIN_ESTIMATED_DURATION_MINUTES = 15;
    public const int MAX_ESTIMATED_DURATION_MINUTES = 1440;
    public const int DEFAULT_CONFLICT_WINDOW_MINUTES = 120;

    public const int DEFAULT_AUTO_FINISH_MINUTES = 720;
    public const float AUTO_FINISH_TOLERANCE_FACTOR = 0.5;
    public const int MIN_AUTO_FINISH_TOLERANCE_MINUTES = 15;
    public const int MAX_AUTO_FINISH_TOLERANCE_MINUTES = 240;
    public const int AUTO_CANCEL_NOT_STARTED_AFTER_MINUTES = 180;

    public const int CHECK_IN_OPENS_BEFORE_MINUTES = 30;
    public const int CHECK_IN_CLOSES_AFTER_MINUTES = 30;
    public const int START_OPENS_BEFORE_MINUTES = 15;
    public const int START_CLOSES_AFTER_MINUTES = 120;
    public const int FINISH_WINDOW_AFTER_START_HOURS = 24;
    public const int VALIDATION_OPENS_AFTER_SCHEDULED_END_MINUTES = 30;
    public const int VALIDATION_CLOSES_AFTER_FINISH_HOURS = 48;
    public const int INVALIDATION_WINDOW_HOURS = 48;

    public const int MIN_CHECKED_IN_FOR_XP = 2;
    public const int CONFLICT_MARGIN_MINUTES = 0;
    public const int AUTO_CLOSE_INTERVAL_MINUTES = 5;

    public static function autoCloseAt(Instant $startedAt, ?int $estimatedDurationMinutes): Instant
    {
        if (null === $estimatedDurationMinutes) {
            return self::addMinutes($startedAt, self::DEFAULT_AUTO_FINISH_MINUTES);
        }

        $tolerance = min(
            self::MAX_AUTO_FINISH_TOLERANCE_MINUTES,
            max(
                self::MIN_AUTO_FINISH_TOLERANCE_MINUTES,
                (int) ceil($estimatedDurationMinutes * self::AUTO_FINISH_TOLERANCE_FACTOR),
            ),
        );

        return self::addMinutes($startedAt, $estimatedDurationMinutes + $tolerance);
    }

    public static function autoCancelNotStartedAt(Instant $startsAt): Instant
    {
        return self::addMinutes($startsAt, self::AUTO_CANCEL_NOT_STARTED_AFTER_MINUTES);
    }

    public static function checkInOpensAt(Instant $startsAt): Instant
    {
        return self::subMinutes($startsAt, self::CHECK_IN_OPENS_BEFORE_MINUTES);
    }

    public static function checkInClosesAt(Instant $scheduledEndsAt): Instant
    {
        return self::addMinutes($scheduledEndsAt, self::CHECK_IN_CLOSES_AFTER_MINUTES);
    }

    public static function startOpensAt(Instant $startsAt): Instant
    {
        return self::subMinutes($startsAt, self::START_OPENS_BEFORE_MINUTES);
    }

    public static function startClosesAt(Instant $startsAt): Instant
    {
        return self::addMinutes($startsAt, self::START_CLOSES_AFTER_MINUTES);
    }

    public static function finishClosesAt(Instant $startedAt): Instant
    {
        return self::addHours($startedAt, self::FINISH_WINDOW_AFTER_START_HOURS);
    }

    public static function validationOpensAt(Instant $finishedAt, Instant $scheduledEndsAt): Instant
    {
        $checkInClose = self::addMinutes($scheduledEndsAt, self::VALIDATION_OPENS_AFTER_SCHEDULED_END_MINUTES);

        return $finishedAt->isAfter($checkInClose) ? $finishedAt : $checkInClose;
    }

    public static function validationClosesAt(Instant $finishedAt): Instant
    {
        return self::addHours($finishedAt, self::VALIDATION_CLOSES_AFTER_FINISH_HOURS);
    }

    public static function invalidationClosesAt(Instant $reference): Instant
    {
        return self::addHours($reference, self::INVALIDATION_WINDOW_HOURS);
    }

    private static function addMinutes(Instant $instant, int $minutes): Instant
    {
        return Instant::fromDateTime(
            $instant->value()->modify(sprintf('+%d minutes', $minutes)),
        );
    }

    private static function subMinutes(Instant $instant, int $minutes): Instant
    {
        return Instant::fromDateTime(
            $instant->value()->modify(sprintf('-%d minutes', $minutes)),
        );
    }

    private static function addHours(Instant $instant, int $hours): Instant
    {
        return Instant::fromDateTime(
            $instant->value()->modify(sprintf('+%d hours', $hours)),
        );
    }
}
