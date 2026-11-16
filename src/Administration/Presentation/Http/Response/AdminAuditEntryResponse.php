<?php

declare(strict_types=1);

namespace App\Administration\Presentation\Http\Response;

use App\Administration\Domain\Audit\Entity\AdminAuditEntry;
use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Entrada da trilha de auditoria da administração.')]
final readonly class AdminAuditEntryResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(example: AdminAuditAction::GroupArchived->value, enum: AdminAuditAction::class)]
        public string $action,

        #[OA\Property(example: AdminAuditTargetType::Group->value, enum: AdminAuditTargetType::class)]
        public string $targetType,

        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $targetId,

        #[OA\Property(description: 'Rótulo do alvo no momento da ação.', example: 'Corrida no Parque')]
        public string $targetLabel,

        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $actorId,

        #[OA\Property(example: 'Ana Administradora')]
        public string $actorName,

        #[OA\Property(description: 'Motivo informado na ação.', example: 'Grupo pausou as atividades.', nullable: true)]
        public ?string $reason,

        #[OA\Property(format: 'date-time', example: '2026-11-15T10:00:00.000+00:00')]
        public string $occurredAt,
    ) {
    }

    public static function fromEntity(AdminAuditEntry $entry): self
    {
        return new self(
            id: $entry->id()->value(),
            action: $entry->action()->value,
            targetType: $entry->targetType()->value,
            targetId: $entry->targetId(),
            targetLabel: $entry->targetLabel(),
            actorId: $entry->actorId(),
            actorName: $entry->actorName(),
            reason: $entry->reason(),
            occurredAt: $entry->occurredAt()->toIsoStringWithMilliseconds(),
        );
    }
}
