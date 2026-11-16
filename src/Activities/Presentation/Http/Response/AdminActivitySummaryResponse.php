<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Response;

use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Resumo de uma atividade na listagem administrativa.')]
final readonly class AdminActivitySummaryResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55e')]
        public string $groupId,

        #[OA\Property(description: 'Nome do grupo resolvido para a listagem.', example: 'Corrida no Parque')]
        public string $groupName,

        #[OA\Property(example: 'Treino de sábado')]
        public string $title,

        #[OA\Property(format: 'date-time', example: '2026-11-15T10:00:00.000+00:00')]
        public string $startsAt,

        #[OA\Property(format: 'date-time', example: '2026-11-15T11:00:00.000+00:00')]
        public string $endsAt,

        #[OA\Property(example: ActivityStatus::SCHEDULED->value, enum: ActivityStatus::class)]
        public string $status,

        #[OA\Property(example: 4)]
        public int $enrolledCount,

        #[OA\Property(example: 3)]
        public int $checkedInCount,

        #[OA\Property(example: 2)]
        public int $validatedCount,

        #[OA\Property(format: 'date-time', example: null, nullable: true)]
        public ?string $cancelledAt,

        #[OA\Property(example: null, nullable: true)]
        public ?string $cancellationReason,

        #[OA\Property(format: 'date-time', example: null, nullable: true)]
        public ?string $invalidatedAt,

        #[OA\Property(example: null, nullable: true)]
        public ?string $invalidationReason,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00')]
        public string $createdAt,
    ) {
    }

    public static function fromEntity(Activity $activity, ?string $groupName): self
    {
        return new self(
            id: $activity->id()->value(),
            groupId: $activity->groupId()->value(),
            groupName: $groupName ?? 'Grupo removido',
            title: $activity->title()->value(),
            startsAt: $activity->window()->startsAt()->toIsoStringWithMilliseconds(),
            endsAt: $activity->window()->endsAt()->toIsoStringWithMilliseconds(),
            status: $activity->status()->value,
            enrolledCount: $activity->enrolledCount(),
            checkedInCount: $activity->checkedInCount(),
            validatedCount: $activity->validatedCount(),
            cancelledAt: $activity->cancelledAt()?->toIsoStringWithMilliseconds(),
            cancellationReason: $activity->cancellationReason(),
            invalidatedAt: $activity->invalidatedAt()?->toIsoStringWithMilliseconds(),
            invalidationReason: $activity->invalidationReason(),
            createdAt: $activity->createdAt()->toIsoStringWithMilliseconds(),
        );
    }
}
