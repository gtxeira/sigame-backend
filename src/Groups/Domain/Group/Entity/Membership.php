<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Entity;

use App\Groups\Domain\Group\ValueObject\MembershipId;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Domain\Group\ValueObject\RemovalReason;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Participação de um usuário em um grupo.
 *
 * Cada solicitação/entrada gera um episódio próprio. Rejeição e remoção
 * preservam a linha com autor, data e motivo, atendendo à auditoria de
 * administração de membros.
 */
#[ORM\Entity]
#[ORM\Table(name: 'group_memberships')]
#[ORM\UniqueConstraint(
    name: 'uniq_group_memberships_active',
    columns: ['group_id', 'user_id'],
    options: ['where' => "(status IN ('PENDING', 'APPROVED'))"],
)]
#[ORM\UniqueConstraint(
    name: 'uniq_group_memberships_supervisor',
    columns: ['group_id'],
    options: ['where' => "(is_supervisor = TRUE AND status = 'APPROVED')"],
)]
#[ORM\Index(name: 'idx_group_memberships_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_group_memberships_group_status', columns: ['group_id', 'status'])]
#[ORM\Index(name: 'idx_group_memberships_group_role_status', columns: ['group_id', 'role', 'status'])]
final class Membership
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'membership_id', unique: true)]
        private MembershipId $id,

        #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'memberships')]
        #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Group $group,

        #[ORM\Column(type: 'member_user_id')]
        private MemberUserId $userId,

        #[ORM\Column(type: 'string', length: 20, enumType: MembershipRole::class)]
        private MembershipRole $role,

        #[ORM\Column(type: 'string', length: 20, enumType: MembershipStatus::class)]
        private MembershipStatus $status,

        #[ORM\Column(type: 'boolean', options: ['default' => false])]
        private bool $isSupervisor,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $requestedAt,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $decidedAt,

        #[ORM\Column(type: 'member_user_id', nullable: true)]
        private ?MemberUserId $decidedBy,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $removedAt,

        #[ORM\Column(type: 'member_user_id', nullable: true)]
        private ?MemberUserId $removedBy,

        #[ORM\Column(type: 'removal_reason', length: 500, nullable: true)]
        private ?RemovalReason $removalReason,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $createdAt,

        #[ORM\Column(type: 'instant')]
        private Instant $updatedAt,
    ) {
    }

    public static function admin(Group $group, MemberUserId $userId): self
    {
        $now = Instant::now();

        return new self(
            id: MembershipId::generate(),
            group: $group,
            userId: $userId,
            role: MembershipRole::ADMIN,
            status: MembershipStatus::APPROVED,
            isSupervisor: false,
            requestedAt: $now,
            decidedAt: $now,
            decidedBy: $userId,
            removedAt: null,
            removedBy: null,
            removalReason: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function pending(Group $group, MemberUserId $userId): self
    {
        $now = Instant::now();

        return new self(
            id: MembershipId::generate(),
            group: $group,
            userId: $userId,
            role: MembershipRole::MEMBER,
            status: MembershipStatus::PENDING,
            isSupervisor: false,
            requestedAt: $now,
            decidedAt: null,
            decidedBy: null,
            removedAt: null,
            removedBy: null,
            removalReason: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function approve(MemberUserId $decidedBy): void
    {
        $this->status = MembershipStatus::APPROVED;
        $this->decidedAt = Instant::now();
        $this->decidedBy = $decidedBy;
        $this->touch();
    }

    public function reject(MemberUserId $decidedBy): void
    {
        $this->status = MembershipStatus::REJECTED;
        $this->decidedAt = Instant::now();
        $this->decidedBy = $decidedBy;
        $this->touch();
    }

    public function remove(MemberUserId $removedBy, RemovalReason $reason): void
    {
        $this->status = MembershipStatus::REMOVED;
        $this->removedAt = Instant::now();
        $this->removedBy = $removedBy;
        $this->removalReason = $reason;
        $this->isSupervisor = false;
        $this->touch();
    }

    public function promote(): void
    {
        if (MembershipRole::ADMIN === $this->role) {
            return;
        }

        $this->role = MembershipRole::ADMIN;
        $this->touch();
    }

    public function demote(): void
    {
        if (MembershipRole::MEMBER === $this->role) {
            return;
        }

        $this->role = MembershipRole::MEMBER;
        $this->touch();
    }

    public function designateSupervisor(): void
    {
        if ($this->isSupervisor) {
            return;
        }

        $this->isSupervisor = true;
        $this->touch();
    }

    public function clearSupervisor(): void
    {
        if (!$this->isSupervisor) {
            return;
        }

        $this->isSupervisor = false;
        $this->touch();
    }

    public function id(): MembershipId
    {
        return $this->id;
    }

    public function group(): Group
    {
        return $this->group;
    }

    public function userId(): MemberUserId
    {
        return $this->userId;
    }

    public function role(): MembershipRole
    {
        return $this->role;
    }

    public function status(): MembershipStatus
    {
        return $this->status;
    }

    public function isAdmin(): bool
    {
        return MembershipRole::ADMIN === $this->role;
    }

    public function isApproved(): bool
    {
        return MembershipStatus::APPROVED === $this->status;
    }

    public function isPending(): bool
    {
        return MembershipStatus::PENDING === $this->status;
    }

    public function isSupervisor(): bool
    {
        return $this->isSupervisor;
    }

    public function requestedAt(): Instant
    {
        return $this->requestedAt;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    public function decidedAt(): ?Instant
    {
        return $this->decidedAt;
    }

    public function decidedBy(): ?MemberUserId
    {
        return $this->decidedBy;
    }

    public function removedAt(): ?Instant
    {
        return $this->removedAt;
    }

    public function removedBy(): ?MemberUserId
    {
        return $this->removedBy;
    }

    public function removalReason(): ?RemovalReason
    {
        return $this->removalReason;
    }

    private function touch(): void
    {
        $this->updatedAt = Instant::now();
    }
}
