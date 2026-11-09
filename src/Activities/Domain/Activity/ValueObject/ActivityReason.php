<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Activities\Domain\Error\ActivityErrorCode;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Motivo textual de cancelamento, remarcação ou invalidação.
 */
final readonly class ActivityReason
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 500;

    private function __construct(private string $value)
    {
    }

    /**
     * @return ValidationResult<self>
     */
    public static function fromString(
        string $value,
        ActivityErrorCode $invalidCode = ActivityErrorCode::ActivityCancelReasonInvalid,
    ): ValidationResult {
        $normalized = trim($value);
        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return ValidationResult::invalid([self::errorFor($invalidCode)]);
        }

        return ValidationResult::valid(new self($normalized));
    }

    public function value(): string
    {
        return $this->value;
    }

    private static function errorFor(ActivityErrorCode $invalidCode): DomainError
    {
        return match ($invalidCode) {
            ActivityErrorCode::ActivityRescheduleReasonInvalid => ActivityErrors::rescheduleReasonInvalid(self::MAX_LENGTH),
            ActivityErrorCode::ActivityInvalidationReasonRequired => ActivityErrors::invalidationReasonRequired(self::MIN_LENGTH, self::MAX_LENGTH),
            default => ActivityErrors::cancelReasonInvalid(self::MAX_LENGTH),
        };
    }
}
