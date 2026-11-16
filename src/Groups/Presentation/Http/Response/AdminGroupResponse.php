<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Shared\Application\User\UserMemberProfile;
use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Dados administrativos de um grupo, incluindo arquivamento e exclusão.')]
final readonly class AdminGroupResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(description: 'Criador do grupo, independente do papel de administrador.', format: 'uuid')]
        public string $createdBy,

        #[OA\Property(description: 'Responsável atual pelo grupo.', format: 'uuid')]
        public string $ownerId,

        #[OA\Property(example: 'Maria')]
        public string $ownerName,

        #[OA\Property(example: 'Corrida no Parque')]
        public string $name,

        #[OA\Property(example: 'Encontros aos sábados pela manhã.', nullable: true)]
        public ?string $description,

        #[OA\Property(example: Modality::RUNNING->value, enum: Modality::class)]
        public string $modality,

        #[OA\Property(example: GroupStatus::ACTIVE->value, enum: GroupStatus::class)]
        public string $status,

        #[OA\Property(example: 5)]
        public int $approvedMembers,

        #[OA\Property(description: 'Limite máximo de membros aprovados. Null quando ilimitado.', example: 8, nullable: true)]
        public ?int $maxMembers,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00', nullable: true)]
        public ?string $activatedAt,

        #[OA\Property(example: false)]
        public bool $archived,

        #[OA\Property(format: 'date-time', example: null, nullable: true)]
        public ?string $archivedAt,

        #[OA\Property(format: 'uuid', example: null, nullable: true)]
        public ?string $archivedBy,

        #[OA\Property(description: 'Nome do administrador que arquivou.', example: null, nullable: true)]
        public ?string $archivedByName,

        #[OA\Property(description: 'Motivo do arquivamento.', example: null, nullable: true)]
        public ?string $archiveReason,

        #[OA\Property(example: false)]
        public bool $deleted,

        #[OA\Property(format: 'date-time', example: null, nullable: true)]
        public ?string $deletedAt,

        #[OA\Property(format: 'uuid', example: null, nullable: true)]
        public ?string $deletedBy,

        #[OA\Property(description: 'Nome do administrador que excluiu.', example: null, nullable: true)]
        public ?string $deletedByName,

        #[OA\Property(description: 'Motivo da exclusão.', example: null, nullable: true)]
        public ?string $deleteReason,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00')]
        public string $createdAt,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00')]
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(
        Group $group,
        ?UserMemberProfile $owner,
        ?UserMemberProfile $archivedBy,
        ?UserMemberProfile $deletedBy,
    ): self {
        return new self(
            id: $group->id()->value(),
            createdBy: $group->createdBy()->value(),
            ownerId: $group->ownerId()->value(),
            ownerName: null === $owner ? 'Participante' : $owner->name,
            name: $group->name()->value(),
            description: $group->description()?->value(),
            modality: $group->modality()->value,
            status: $group->status()->value,
            approvedMembers: $group->approvedCount(),
            maxMembers: $group->maxMembers()?->value(),
            activatedAt: $group->activatedAt()?->toIsoStringWithMilliseconds(),
            archived: $group->isArchived(),
            archivedAt: $group->archivedAt()?->toIsoStringWithMilliseconds(),
            archivedBy: $group->archivedBy()?->value(),
            archivedByName: $archivedBy?->name,
            archiveReason: $group->archiveReason(),
            deleted: $group->isDeleted(),
            deletedAt: $group->deletedAt()?->toIsoStringWithMilliseconds(),
            deletedBy: $group->deletedBy()?->value(),
            deletedByName: $deletedBy?->name,
            deleteReason: $group->deleteReason(),
            createdAt: $group->createdAt()->toIsoStringWithMilliseconds(),
            updatedAt: $group->updatedAt()->toIsoStringWithMilliseconds(),
        );
    }
}
