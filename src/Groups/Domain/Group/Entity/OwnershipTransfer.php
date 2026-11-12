<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Entity;

use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Domain\Group\ValueObject\OwnershipTransferId;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Registro append-only de transferência de responsabilidade pelo grupo.
 *
 * A responsabilidade só muda por ação explícita do responsável atual; este
 * histórico preserva quem transferiu, para quem e quando.
 */
#[ORM\Entity]
#[ORM\Table(name: 'group_ownership_transfers')]
#[ORM\Index(name: 'idx_group_ownership_transfers_group', columns: ['group_id', 'transferred_at'])]
final class OwnershipTransfer
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'ownership_transfer_id', unique: true)]
        private OwnershipTransferId $id,

        #[ORM\ManyToOne(targetEntity: Group::class)]
        #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Group $group,

        #[ORM\Column(type: 'member_user_id')]
        private MemberUserId $previousOwnerId,

        #[ORM\Column(type: 'member_user_id')]
        private MemberUserId $newOwnerId,

        #[ORM\Column(type: 'member_user_id')]
        private MemberUserId $actorId,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $transferredAt,
    ) {
    }

    public static function record(
        Group $group,
        MemberUserId $previousOwnerId,
        MemberUserId $newOwnerId,
        MemberUserId $actorId,
    ): self {
        return new self(
            id: OwnershipTransferId::generate(),
            group: $group,
            previousOwnerId: $previousOwnerId,
            newOwnerId: $newOwnerId,
            actorId: $actorId,
            transferredAt: Instant::now(),
        );
    }

    public function id(): OwnershipTransferId
    {
        return $this->id;
    }

    public function groupId(): GroupId
    {
        return $this->group->id();
    }

    public function previousOwnerId(): MemberUserId
    {
        return $this->previousOwnerId;
    }

    public function newOwnerId(): MemberUserId
    {
        return $this->newOwnerId;
    }

    public function actorId(): MemberUserId
    {
        return $this->actorId;
    }

    public function transferredAt(): Instant
    {
        return $this->transferredAt;
    }
}
