<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Shared\Application\User\UserMemberProfile;
use OpenApi\Attributes as OA;

/**
 * Membro aprovado de um grupo, na visão de quem participa dele.
 *
 * Só expõe dados públicos (RNF01): nome, nível, foto e papel no grupo.
 */
#[OA\Schema(description: 'Membro aprovado de um grupo.')]
final readonly class GroupMemberResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $membershipId,

        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $userId,

        #[OA\Property(example: 'Maria')]
        public string $firstName,

        #[OA\Property(example: 4)]
        public int $level,

        #[OA\Property(example: null, nullable: true)]
        public ?string $photoUrl,

        #[OA\Property(example: MembershipRole::MEMBER, enum: MembershipRole::class)]
        public string $role,

        #[OA\Property(example: false)]
        public bool $isSupervisor,

        #[OA\Property(example: false)]
        public bool $isOwner,
    ) {
    }

    public static function fromEntity(Membership $membership, ?UserMemberProfile $profile): self
    {
        return new self(
            membershipId: $membership->id()->value(),
            userId: $membership->userId()->value(),
            firstName: self::firstName($profile?->name),
            level: null === $profile ? 1 : $profile->level,
            photoUrl: null === $profile ? null : $profile->photoUrl,
            role: $membership->role()->value,
            isSupervisor: $membership->isSupervisor(),
            isOwner: $membership->group()->isOwnedBy($membership->userId()),
        );
    }

    private static function firstName(?string $name): string
    {
        if (null === $name) {
            return 'Participante';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return $parts[0] ?? 'Participante';
    }
}
