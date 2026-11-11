<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Activities\Domain\Activity\Policy\ActivityPolicy;
use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Duração média estimada de uma atividade, em minutos.
 */
final readonly class EstimatedDuration
{
    private function __construct(
        private int $minutes,
    ) {
    }

    /**
     * @return ValidationResult<self|null>
     */
    public static function fromMinutes(?int $minutes): ValidationResult
    {
        if (null === $minutes) {
            /** @var ValidationResult<self|null> $result */
            $result = ValidationResult::valid(null);

            return $result;
        }

        if ($minutes < ActivityPolicy::MIN_ESTIMATED_DURATION_MINUTES
            || $minutes > ActivityPolicy::MAX_ESTIMATED_DURATION_MINUTES
        ) {
            return ValidationResult::invalid([
                ValidationErrors::numberOutOfRange(
                    'estimatedDurationMinutes',
                    sprintf(
                        'A duração estimada deve estar entre %d e %d minutos.',
                        ActivityPolicy::MIN_ESTIMATED_DURATION_MINUTES,
                        ActivityPolicy::MAX_ESTIMATED_DURATION_MINUTES,
                    ),
                ),
            ]);
        }

        /** @var ValidationResult<self|null> $result */
        $result = ValidationResult::valid(new self($minutes));

        return $result;
    }

    public function minutes(): int
    {
        return $this->minutes;
    }
}
