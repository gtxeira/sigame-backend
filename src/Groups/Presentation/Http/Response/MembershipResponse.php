<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use App\Shared\Application\User\UserMemberProfile;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Participação de um usuário em um grupo.')]
final readonly class MembershipResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $groupId,

        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $userId,

        #[OA\Property(example: MembershipStatus::PENDING, enum: MembershipStatus::class)]
        public string $status,

        #[OA\Property(example: MembershipRole::MEMBER, enum: MembershipRole::class)]
        public string $role,

        #[OA\Property(example: false)]
        public bool $isSupervisor,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00')]
        public string $requestedAt,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:05:00.000+00:00', nullable: true)]
        public ?string $decidedAt,

        #[OA\Property(format: 'date-time', example: null, nullable: true)]
        public ?string $removedAt,

        #[OA\Property(description: 'Motivo registrado na remoção.', example: null, nullable: true)]
        public ?string $removalReason,

        #[OA\Property(
            description: 'Dados públicos do usuário da participação, quando resolvidos.',
            nullable: true,
        )]
        public ?MembershipUserResponse $user,
    ) {
    }

    public static function fromEntity(Membership $membership, ?UserMemberProfile $profile = null): self
    {
        return new self(
            id: $membership->id()->value(),
            groupId: $membership->group()->id()->value(),
            userId: $membership->userId()->value(),
            status: $membership->status()->value,
            role: $membership->role()->value,
            isSupervisor: $membership->isSupervisor(),
            requestedAt: $membership->requestedAt()->toIsoStringWithMilliseconds(),
            decidedAt: $membership->decidedAt()?->toIsoStringWithMilliseconds(),
            removedAt: $membership->removedAt()?->toIsoStringWithMilliseconds(),
            removalReason: $membership->removalReason()?->value(),
            user: null === $profile ? null : MembershipUserResponse::fromProfile($profile),
        );
    }
}
