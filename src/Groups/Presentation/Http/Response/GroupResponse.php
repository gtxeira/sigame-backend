<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Domain\ValueObject\Modality;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Dados de um grupo.')]
final readonly class GroupResponse
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

        #[OA\Property(example: GroupPolicy::MAX_APPROVED_MEMBERS)]
        public int $maxMembers,

        #[OA\Property(
            description: 'Link externo de comunicação. Presente apenas para membros aprovados.',
            example: 'https://chat.example.com/grupo/corrida-no-parque',
            nullable: true,
        )]
        public ?string $communicationLink,

        #[OA\Property(
            description: 'Participação do usuário autenticado, quando houver.',
            ref: new Model(type: ViewerMembershipResponse::class),
            nullable: true,
        )]
        public ?ViewerMembershipResponse $viewerMembership,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00')]
        public string $createdAt,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00')]
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Group $group, MemberUserId $viewer): self
    {
        $membership = $group->membershipFor($viewer);
        $canSeeLink = $group->hasApprovedMembership($viewer);

        return new self(
            id: $group->id()->value(),
            createdBy: $group->createdBy()->value(),
            name: $group->name()->value(),
            description: $group->description()?->value(),
            modality: $group->modality()->value,
            status: $group->status()->value,
            approvedMembers: $group->approvedCount(),
            maxMembers: GroupPolicy::MAX_APPROVED_MEMBERS,
            communicationLink: $canSeeLink ? $group->communicationLink()?->value() : null,
            viewerMembership: null === $membership ? null : ViewerMembershipResponse::fromEntity($membership),
            createdAt: $group->createdAt()->toIsoStringWithMilliseconds(),
            updatedAt: $group->updatedAt()->toIsoStringWithMilliseconds(),
        );
    }
}
