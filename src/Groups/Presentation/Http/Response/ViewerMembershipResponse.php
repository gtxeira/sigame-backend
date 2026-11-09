<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Participação do usuário autenticado no grupo.')]
final readonly class ViewerMembershipResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(example: MembershipStatus::APPROVED, enum: MembershipStatus::class)]
        public string $status,

        #[OA\Property(example: MembershipRole::MEMBER, enum: MembershipRole::class)]
        public string $role,

        #[OA\Property(example: false)]
        public bool $isSupervisor,
    ) {
    }

    public static function fromEntity(Membership $membership): self
    {
        return new self(
            id: $membership->id()->value(),
            status: $membership->status()->value,
            role: $membership->role()->value,
            isSupervisor: $membership->isSupervisor(),
        );
    }
}
