<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Response;

use App\Activities\Domain\Activity\Entity\ActivityReschedule;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Registro de remarcação de atividade.')]
final readonly class ActivityRescheduleResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'uuid')]
        public string $changedBy,
        #[OA\Property(format: 'date-time')]
        public string $previousStartsAt,
        #[OA\Property(format: 'date-time')]
        public string $previousEndsAt,
        #[OA\Property(format: 'date-time')]
        public string $newStartsAt,
        #[OA\Property(format: 'date-time')]
        public string $newEndsAt,
        #[OA\Property(nullable: true)]
        public ?string $reason,
        #[OA\Property(format: 'date-time')]
        public string $changedAt,
    ) {
    }

    public static function fromEntity(ActivityReschedule $reschedule): self
    {
        return new self(
            id: $reschedule->id()->value(),
            changedBy: $reschedule->changedBy()->value(),
            previousStartsAt: $reschedule->previousStartsAt()->toIsoStringWithMilliseconds(),
            previousEndsAt: $reschedule->previousEndsAt()->toIsoStringWithMilliseconds(),
            newStartsAt: $reschedule->newStartsAt()->toIsoStringWithMilliseconds(),
            newEndsAt: $reschedule->newEndsAt()->toIsoStringWithMilliseconds(),
            reason: $reschedule->reason(),
            changedAt: $reschedule->changedAt()->toIsoStringWithMilliseconds(),
        );
    }
}
