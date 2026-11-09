<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Activities\Domain\Activity\Policy\ActivityPolicy;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Domain\Functional\ValidationResult;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Janela planejada de uma atividade.
 *
 * Quando a duração média não é informada, a janela de planejamento usa o
 * padrão de 120 minutos. O fechamento automático é uma regra separada.
 */
final readonly class ActivityWindow
{
    private function __construct(
        private Instant $startsAt,
        private Instant $endsAt,
        private ?int $estimatedDurationMinutes,
    ) {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromStart(
        Instant $startsAt,
        ?int $estimatedDurationMinutes,
    ): ValidationResult {
        if (null !== $estimatedDurationMinutes
            && ($estimatedDurationMinutes < ActivityPolicy::MIN_ESTIMATED_DURATION_MINUTES
                || $estimatedDurationMinutes > ActivityPolicy::MAX_ESTIMATED_DURATION_MINUTES)
        ) {
            return ValidationResult::invalid([
                ActivityErrors::windowInvalid(
                    ActivityPolicy::MIN_ESTIMATED_DURATION_MINUTES,
                    ActivityPolicy::MAX_ESTIMATED_DURATION_MINUTES,
                ),
            ]);
        }

        $plannedMinutes = $estimatedDurationMinutes ?? ActivityPolicy::DEFAULT_CONFLICT_WINDOW_MINUTES;
        $endsAt = Instant::fromDateTime(
            $startsAt->value()->modify(sprintf('+%d minutes', $plannedMinutes)),
        );

        if (!$endsAt->isAfter($startsAt)) {
            return ValidationResult::invalid([
                ActivityErrors::windowInvalid(
                    ActivityPolicy::MIN_ESTIMATED_DURATION_MINUTES,
                    ActivityPolicy::MAX_ESTIMATED_DURATION_MINUTES,
                ),
            ]);
        }

        return ValidationResult::valid(new self($startsAt, $endsAt, $estimatedDurationMinutes));
    }

    public static function restore(
        Instant $startsAt,
        Instant $endsAt,
        ?int $estimatedDurationMinutes,
    ): self {
        return new self($startsAt, $endsAt, $estimatedDurationMinutes);
    }

    public function startsAt(): Instant
    {
        return $this->startsAt;
    }

    public function endsAt(): Instant
    {
        return $this->endsAt;
    }

    public function estimatedDurationMinutes(): ?int
    {
        return $this->estimatedDurationMinutes;
    }

    public function plannedDurationMinutes(): int
    {
        return $this->estimatedDurationMinutes ?? ActivityPolicy::DEFAULT_CONFLICT_WINDOW_MINUTES;
    }

    public function overlaps(self $other): bool
    {
        return $this->startsAt->isBefore($other->endsAt)
            && $this->endsAt->isAfter($other->startsAt);
    }
}
