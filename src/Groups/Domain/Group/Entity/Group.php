<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Entity;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Groups\Domain\Group\ValueObject\CommunicationLink;
use App\Groups\Domain\Group\ValueObject\GroupDescription;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\MembershipId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Domain\Group\ValueObject\RemovalReason;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Domain\ValueObject\Modality;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Raiz do agregado de grupos esportivos.
 *
 * O criador é registrado em `createdBy`, independente do papel de
 * administrador. O grupo pode ter vários administradores aprovados, mas nunca
 * fica sem ao menos um.
 */
#[ORM\Entity]
#[ORM\Table(name: 'groups')]
#[ORM\Index(name: 'idx_groups_created_by', columns: ['created_by'])]
final class Group
{
    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(
        targetEntity: Membership::class,
        mappedBy: 'group',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $memberships;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'group_id', unique: true)]
        private GroupId $id,

        #[ORM\Column(type: 'member_user_id')]
        private readonly MemberUserId $createdBy,

        #[ORM\Column(type: 'group_name', length: 80)]
        private GroupName $name,

        #[ORM\Column(type: 'group_description', length: 500, nullable: true)]
        private ?GroupDescription $description,

        #[ORM\Column(type: 'string', length: 20, enumType: Modality::class)]
        private Modality $modality,

        #[ORM\Column(type: 'group_communication_link', length: 2048, nullable: true)]
        private ?CommunicationLink $communicationLink,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $createdAt,

        #[ORM\Column(type: 'instant')]
        private Instant $updatedAt,
    ) {
        $this->memberships = new ArrayCollection();
    }

    /**
     * Cria o grupo e registra o criador como primeiro administrador (RN04.2).
     */
    public static function create(
        GroupName $name,
        ?GroupDescription $description,
        Modality $modality,
        ?CommunicationLink $communicationLink,
        MemberUserId $createdBy,
    ): self {
        $now = Instant::now();

        $group = new self(
            id: GroupId::generate(),
            createdBy: $createdBy,
            name: $name,
            description: $description,
            modality: $modality,
            communicationLink: $communicationLink,
            createdAt: $now,
            updatedAt: $now,
        );

        $group->memberships->add(Membership::admin($group, $createdBy));

        return $group;
    }

    /**
     * Registra uma solicitação de entrada pendente (RF05).
     *
     * @return Result<Membership>
     */
    public function requestMembership(MemberUserId $userId): Result
    {
        if (null !== $this->membershipFor($userId)) {
            return Result::failure([GroupErrors::membershipAlreadyExists()]);
        }

        $membership = Membership::pending($this, $userId);
        $this->memberships->add($membership);
        $this->touch();

        return Result::success($membership);
    }

    /**
     * Aprova uma solicitação pendente (RF06).
     *
     * RN06.5: respeita o limite de 5 membros aprovados.
     * RN06.6: respeita o limite de 3 grupos do usuário.
     *
     * @return Result<Membership>
     */
    public function approveMembership(
        MembershipId $membershipId,
        MemberUserId $actor,
        int $activeGroupCount,
    ): Result {
        $adminError = $this->assertAdmin($actor);

        if (null !== $adminError) {
            return Result::failure([$adminError]);
        }

        $membership = $this->membershipById($membershipId);

        if (null === $membership) {
            return Result::failure([GroupErrors::membershipNotFound()]);
        }

        if (!$membership->isPending()) {
            return Result::failure([GroupErrors::membershipNotPending()]);
        }

        if (GroupPolicy::isFull($this->approvedCount())) {
            return Result::failure([GroupErrors::groupFull(GroupPolicy::MAX_APPROVED_MEMBERS)]);
        }

        if ($activeGroupCount >= GroupPolicy::MAX_GROUPS_PER_USER) {
            return Result::failure([GroupErrors::limitReached(GroupPolicy::MAX_GROUPS_PER_USER)]);
        }

        $membership->approve($actor);
        $this->touch();

        return Result::success($membership);
    }

    /**
     * Rejeita uma solicitação pendente.
     *
     * @return Result<Membership>
     */
    public function rejectMembership(MembershipId $membershipId, MemberUserId $actor): Result
    {
        $adminError = $this->assertAdmin($actor);

        if (null !== $adminError) {
            return Result::failure([$adminError]);
        }

        $membership = $this->membershipById($membershipId);

        if (null === $membership) {
            return Result::failure([GroupErrors::membershipNotFound()]);
        }

        if (!$membership->isPending()) {
            return Result::failure([GroupErrors::membershipNotPending()]);
        }

        $membership->reject($actor);
        $this->touch();

        return Result::success($membership);
    }

    /**
     * Remove um membro aprovado, registrando autor, data e motivo (RN06.2/RN06.3).
     *
     * @return Result<Membership>
     */
    public function removeMembership(
        MembershipId $membershipId,
        MemberUserId $actor,
        RemovalReason $reason,
    ): Result {
        $adminError = $this->assertAdmin($actor);

        if (null !== $adminError) {
            return Result::failure([$adminError]);
        }

        $membership = $this->membershipById($membershipId);

        if (null === $membership) {
            return Result::failure([GroupErrors::membershipNotFound()]);
        }

        if (!$membership->isApproved()) {
            return Result::failure([GroupErrors::membershipNotApproved()]);
        }

        if ($this->isLastAdmin($membership)) {
            return Result::failure([GroupErrors::lastAdminRequired()]);
        }

        $membership->remove($actor, $reason);
        $this->touch();

        return Result::success($membership);
    }

    /**
     * Promove um membro aprovado a administrador.
     *
     * @return Result<Membership>
     */
    public function promoteToAdmin(MembershipId $membershipId, MemberUserId $actor): Result
    {
        $adminError = $this->assertAdmin($actor);

        if (null !== $adminError) {
            return Result::failure([$adminError]);
        }

        $membership = $this->membershipById($membershipId);

        if (null === $membership) {
            return Result::failure([GroupErrors::membershipNotFound()]);
        }

        if (!$membership->isApproved()) {
            return Result::failure([GroupErrors::membershipNotApproved()]);
        }

        $membership->promote();
        $this->touch();

        return Result::success($membership);
    }

    /**
     * Rebaixa um administrador para membro, preservando ao menos um admin.
     *
     * @return Result<Membership>
     */
    public function demoteToMember(MembershipId $membershipId, MemberUserId $actor): Result
    {
        $adminError = $this->assertAdmin($actor);

        if (null !== $adminError) {
            return Result::failure([$adminError]);
        }

        $membership = $this->membershipById($membershipId);

        if (null === $membership) {
            return Result::failure([GroupErrors::membershipNotFound()]);
        }

        if (!$membership->isApproved()) {
            return Result::failure([GroupErrors::membershipNotApproved()]);
        }

        if ($this->isLastAdmin($membership)) {
            return Result::failure([GroupErrors::lastAdminRequired()]);
        }

        $membership->demote();
        $this->touch();

        return Result::success($membership);
    }

    /**
     * Designa um membro aprovado como supervisor, removendo o anterior.
     *
     * @return Result<Membership>
     */
    public function designateSupervisor(MembershipId $membershipId, MemberUserId $actor): Result
    {
        $adminError = $this->assertAdmin($actor);

        if (null !== $adminError) {
            return Result::failure([$adminError]);
        }

        $membership = $this->membershipById($membershipId);

        if (null === $membership) {
            return Result::failure([GroupErrors::membershipNotFound()]);
        }

        if (!$membership->isApproved()) {
            return Result::failure([GroupErrors::supervisorNotMember()]);
        }

        foreach ($this->memberships as $current) {
            if ($current !== $membership && $current->isSupervisor()) {
                $current->clearSupervisor();
            }
        }

        $membership->designateSupervisor();
        $this->touch();

        return Result::success($membership);
    }

    /**
     * Remove a designação de supervisor, de forma idempotente.
     *
     * @return Result<bool> `true` quando havia supervisor
     */
    public function clearSupervisor(MemberUserId $actor): Result
    {
        $adminError = $this->assertAdmin($actor);

        if (null !== $adminError) {
            return Result::failure([$adminError]);
        }

        $changed = false;

        foreach ($this->memberships as $membership) {
            if ($membership->isSupervisor()) {
                $membership->clearSupervisor();
                $changed = true;
            }
        }

        if ($changed) {
            $this->touch();
        }

        return Result::success($changed);
    }

    /**
     * Atualiza os dados editáveis do grupo.
     *
     * @return Result<$this>
     */
    public function changeDetails(
        GroupName $name,
        ?GroupDescription $description,
        Modality $modality,
        ?CommunicationLink $communicationLink,
        MemberUserId $actor,
    ): Result {
        $adminError = $this->assertAdmin($actor);

        if (null !== $adminError) {
            return Result::failure([$adminError]);
        }

        $this->name = $name;
        $this->description = $description;
        $this->modality = $modality;
        $this->communicationLink = $communicationLink;
        $this->touch();

        return Result::success($this);
    }

    public function id(): GroupId
    {
        return $this->id;
    }

    public function createdBy(): MemberUserId
    {
        return $this->createdBy;
    }

    public function name(): GroupName
    {
        return $this->name;
    }

    public function description(): ?GroupDescription
    {
        return $this->description;
    }

    public function modality(): Modality
    {
        return $this->modality;
    }

    public function communicationLink(): ?CommunicationLink
    {
        return $this->communicationLink;
    }

    /**
     * @return list<Membership>
     */
    public function memberships(): array
    {
        $memberships = array_values($this->memberships->toArray());

        usort(
            $memberships,
            static fn (Membership $a, Membership $b): int => $a->requestedAt()->format('U.u') <=> $b->requestedAt()->format('U.u'),
        );

        return $memberships;
    }

    public function membershipById(MembershipId $membershipId): ?Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->id()->equals($membershipId)) {
                return $membership;
            }
        }

        return null;
    }

    /**
     * Participação pendente ou aprovada do usuário, quando existir.
     */
    public function membershipFor(MemberUserId $userId): ?Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->userId()->equals($userId) && $membership->status()->isActive()) {
                return $membership;
            }
        }

        return null;
    }

    public function approvedMembershipOf(MemberUserId $userId): ?Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->userId()->equals($userId) && $membership->isApproved()) {
                return $membership;
            }
        }

        return null;
    }

    public function hasApprovedMembership(MemberUserId $userId): bool
    {
        return null !== $this->approvedMembershipOf($userId);
    }

    public function supervisor(): ?Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->isSupervisor()) {
                return $membership;
            }
        }

        return null;
    }

    public function approvedCount(): int
    {
        $count = 0;

        foreach ($this->memberships as $membership) {
            if ($membership->isApproved()) {
                ++$count;
            }
        }

        return $count;
    }

    public function approvedAdminCount(): int
    {
        $count = 0;

        foreach ($this->memberships as $membership) {
            if ($membership->isApproved() && $membership->isAdmin()) {
                ++$count;
            }
        }

        return $count;
    }

    public function status(): GroupStatus
    {
        $approved = $this->approvedCount();

        if (GroupPolicy::isFull($approved)) {
            return GroupStatus::FULL;
        }

        if (GroupPolicy::isActive($approved)) {
            return GroupStatus::ACTIVE;
        }

        return GroupStatus::FORMING;
    }

    public function isActive(): bool
    {
        return $this->status()->canReceiveActivity();
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    public function isLastAdmin(Membership $membership): bool
    {
        return $membership->isApproved()
            && $membership->isAdmin()
            && $this->approvedAdminCount() <= 1;
    }

    public function canBeManagedBy(MemberUserId $actor): bool
    {
        $membership = $this->approvedMembershipOf($actor);

        return null !== $membership && $membership->isAdmin();
    }

    private function assertAdmin(MemberUserId $actor): ?DomainError
    {
        $membership = $this->approvedMembershipOf($actor);

        if (null === $membership || !$membership->isAdmin()) {
            return GroupErrors::adminRequired();
        }

        return null;
    }

    private function touch(): void
    {
        $this->updatedAt = Instant::now();
    }
}
