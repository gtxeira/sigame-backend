<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Entity;

use App\Identity\Domain\User\ValueObject\SuspensionAction;
use App\Identity\Domain\User\ValueObject\UserSuspensionId;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Registro append-only de suspensão/reativação de conta.
 *
 * O motivo é público ao titular; anotações internas não fazem parte do modelo.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_suspensions')]
#[ORM\Index(name: 'idx_user_suspensions_user_occurred', columns: ['user_id', 'occurred_at'])]
final class UserSuspension
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'user_suspension_id', unique: true)]
        private UserSuspensionId $id,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private User $user,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'actor_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
        private User $actor,

        #[ORM\Column(type: 'string', length: 20, enumType: SuspensionAction::class)]
        private SuspensionAction $action,

        #[ORM\Column(type: 'string', length: 500, nullable: true)]
        private ?string $reason,

        #[ORM\Column(type: 'instant')]
        private Instant $occurredAt,
    ) {
    }

    public static function record(
        User $user,
        User $actor,
        SuspensionAction $action,
        ?string $reason,
        Instant $occurredAt,
    ): self {
        return new self(
            id: UserSuspensionId::generate(),
            user: $user,
            actor: $actor,
            action: $action,
            reason: $reason,
            occurredAt: $occurredAt,
        );
    }

    public function id(): UserSuspensionId
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function actor(): User
    {
        return $this->actor;
    }

    public function action(): SuspensionAction
    {
        return $this->action;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function occurredAt(): Instant
    {
        return $this->occurredAt;
    }
}
