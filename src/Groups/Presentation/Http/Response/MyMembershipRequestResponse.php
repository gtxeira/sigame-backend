<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use App\Groups\Domain\Group\ValueObject\RemovalReason;
use App\Shared\Application\User\UserContact;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Solicitação ou participação do próprio usuário em um grupo.')]
final readonly class MyMembershipRequestResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $groupId,

        #[OA\Property(example: 'Corrida no Parque')]
        public string $groupName,

        #[OA\Property(description: 'Falso quando o grupo está arquivado (somente leitura).', example: true)]
        public bool $groupActive,

        #[OA\Property(example: MembershipStatus::PENDING, enum: MembershipStatus::class)]
        public string $status,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:00:00.000+00:00')]
        public string $requestedAt,

        #[OA\Property(format: 'date-time', example: '2026-11-09T10:05:00.000+00:00', nullable: true)]
        public ?string $decidedAt,

        #[OA\Property(
            description: 'Primeiro nome de quem decidiu, quando houver.',
            example: 'Maria',
            nullable: true,
        )]
        public ?string $decidedByFirstName,

        #[OA\Property(
            description: 'Verdadeiro quando o próprio usuário cancelou a solicitação pendente.',
            example: false,
        )]
        public bool $withdrawn,
    ) {
    }

    public static function fromEntity(Membership $membership, ?UserContact $decidedBy): self
    {
        $group = $membership->group();

        return new self(
            id: $membership->id()->value(),
            groupId: $group->id()->value(),
            groupName: $group->name()->value(),
            groupActive: !$group->isArchived(),
            status: $membership->status()->value,
            requestedAt: $membership->requestedAt()->toIsoStringWithMilliseconds(),
            decidedAt: $membership->decidedAt()?->toIsoStringWithMilliseconds(),
            decidedByFirstName: null === $decidedBy ? null : self::firstName($decidedBy->name),
            withdrawn: $membership->status() === MembershipStatus::REMOVED
                && $membership->removalReason()?->value() === RemovalReason::requestWithdrawn()->value(),
        );
    }

    private static function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return $parts[0] ?? '';
    }
}
