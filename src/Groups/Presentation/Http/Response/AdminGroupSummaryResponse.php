<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Shared\Application\User\UserMemberProfile;
use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Resumo de um grupo na listagem administrativa.')]
final readonly class AdminGroupSummaryResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

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

        #[OA\Property(example: false)]
        public bool $archived,

        #[OA\Property(format: 'date-time', example: null, nullable: true)]
        public ?string $archivedAt,

        #[OA\Property(example: false)]
        public bool $deleted,

        #[OA\Property(format: 'date-time', example: null, nullable: true)]
        public ?string $deletedAt,

        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $ownerId,

        #[OA\Property(description: 'Nome do responsável resolvido para a listagem.', example: 'Maria')]
        public string $ownerName,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00')]
        public string $createdAt,
    ) {
    }

    public static function fromEntity(Group $group, ?UserMemberProfile $owner): self
    {
        return new self(
            id: $group->id()->value(),
            name: $group->name()->value(),
            description: $group->description()?->value(),
            modality: $group->modality()->value,
            status: $group->status()->value,
            approvedMembers: $group->approvedCount(),
            maxMembers: $group->maxMembers()?->value(),
            archived: $group->isArchived(),
            archivedAt: $group->archivedAt()?->toIsoStringWithMilliseconds(),
            deleted: $group->isDeleted(),
            deletedAt: $group->deletedAt()?->toIsoStringWithMilliseconds(),
            ownerId: $group->ownerId()->value(),
            ownerName: null === $owner ? 'Participante' : $owner->name,
            createdAt: $group->createdAt()->toIsoStringWithMilliseconds(),
        );
    }
}
