<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Application\Activity\NextGroupActivity;
use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Resumo de um grupo na listagem.')]
final readonly class GroupSummaryResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(description: 'Criador do grupo, independente do papel de administrador.', format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $createdBy,

        #[OA\Property(example: 'Corrida no Parque')]
        public string $name,

        #[OA\Property(example: 'Encontros aos sábados pela manhã.', nullable: true)]
        public ?string $description,

        #[OA\Property(example: Modality::RUNNING->value, enum: Modality::class)]
        public string $modality,

        #[OA\Property(example: GroupStatus::FORMING, enum: GroupStatus::class)]
        public string $status,

        #[OA\Property(example: 1)]
        public int $approvedMembers,

        #[OA\Property(
            description: 'Data em que o grupo atingiu o mínimo de membros aprovados. Null enquanto está em formação.',
            format: 'date-time',
            example: '2026-11-09T10:00:00.000+00:00',
            nullable: true,
        )]
        public ?string $activatedAt,

        #[OA\Property(description: 'Indica que o grupo está arquivado e em somente leitura.', example: false)]
        public bool $archived,

        #[OA\Property(
            description: 'Data do arquivamento. Null quando o grupo não está arquivado.',
            format: 'date-time',
            example: null,
            nullable: true,
        )]
        public ?string $archivedAt,

        #[OA\Property(
            description: 'Limite máximo de membros aprovados. Null quando o grupo é ilimitado.',
            example: 8,
            nullable: true,
        )]
        public ?int $maxMembers,

        #[OA\Property(
            description: 'Próxima atividade em andamento ou prevista. Null quando o grupo não tem atividade.',
            nullable: true,
        )]
        public ?GroupNextActivityResponse $nextActivity,

        #[OA\Property(
            description: 'Situação da participação do usuário autenticado, quando houver.',
            example: null,
            nullable: true,
        )]
        public ?string $viewerMembershipStatus,

        #[OA\Property(
            description: 'Papel do usuário no grupo, quando há participação ativa.',
            example: null,
            nullable: true,
            enum: ['admin', 'member'],
        )]
        public ?string $viewerMembershipRole,

        #[OA\Property(description: 'Verdadeiro quando o usuário é o supervisor designado do grupo.', example: false)]
        public bool $viewerIsSupervisor,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00')]
        public string $createdAt,
    ) {
    }

    public static function fromEntity(
        Group $group,
        MemberUserId $viewer,
        ?NextGroupActivity $nextActivity = null,
    ): self {
        $membership = $group->membershipFor($viewer);
        $isApprovedMember = MembershipStatus::APPROVED->value === $membership?->status()->value;

        return new self(
            id: $group->id()->value(),
            createdBy: $group->createdBy()->value(),
            name: $group->name()->value(),
            description: $group->description()?->value(),
            modality: $group->modality()->value,
            status: $group->status()->value,
            approvedMembers: $group->approvedCount(),
            activatedAt: $group->activatedAt()?->toIsoStringWithMilliseconds(),
            archived: $group->isArchived(),
            archivedAt: $group->archivedAt()?->toIsoStringWithMilliseconds(),
            maxMembers: $group->maxMembers()?->value(),
            nextActivity: null === $nextActivity ? null : new GroupNextActivityResponse(
                activityId: $nextActivity->activityId,
                // Título só para membro aprovado; na descoberta pública fica oculto.
                title: $isApprovedMember ? $nextActivity->title : null,
                status: $nextActivity->status,
                startsAt: $nextActivity->startsAt,
            ),
            viewerMembershipStatus: $membership?->status()->value,
            viewerMembershipRole: $membership?->role()->value,
            viewerIsSupervisor: true === $membership?->isSupervisor(),
            createdAt: $group->createdAt()->toIsoStringWithMilliseconds(),
        );
    }
}
