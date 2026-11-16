<?php

declare(strict_types=1);

namespace App\Administration\Domain\Audit\Entity;

use App\Administration\Domain\Audit\ValueObject\AdminAuditEntryId;
use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Registro append-only de uma ação da administração SIGAME.
 *
 * Guarda um snapshot do rótulo do alvo e do nome do ator, para continuar
 * legível depois que o grupo for excluído ou o nome mudar. Não há FK para o
 * alvo: a trilha sobrevive à exclusão lógica (ADR-048).
 */
#[ORM\Entity]
#[ORM\Table(name: 'admin_audit_entries')]
#[ORM\Index(name: 'idx_admin_audit_entries_occurred', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_admin_audit_entries_target', columns: ['target_type', 'target_id'])]
#[ORM\Index(name: 'idx_admin_audit_entries_actor', columns: ['actor_id'])]
#[ORM\Index(name: 'idx_admin_audit_entries_action', columns: ['action'])]
final class AdminAuditEntry
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'admin_audit_entry_id', unique: true)]
        private AdminAuditEntryId $id,

        #[ORM\Column(type: 'string', length: 40, enumType: AdminAuditAction::class)]
        private AdminAuditAction $action,

        #[ORM\Column(type: 'string', length: 20, enumType: AdminAuditTargetType::class)]
        private AdminAuditTargetType $targetType,

        #[ORM\Column(type: 'guid')]
        private string $targetId,

        #[ORM\Column(type: 'string', length: 160)]
        private string $targetLabel,

        #[ORM\Column(type: 'guid')]
        private string $actorId,

        #[ORM\Column(type: 'string', length: 160)]
        private string $actorName,

        #[ORM\Column(type: 'string', length: 500, nullable: true)]
        private ?string $reason,

        #[ORM\Column(type: 'instant')]
        private Instant $occurredAt,
    ) {
    }

    public static function record(
        AdminAuditAction $action,
        AdminAuditTargetType $targetType,
        string $targetId,
        string $targetLabel,
        string $actorId,
        string $actorName,
        ?string $reason,
        Instant $occurredAt,
    ): self {
        return new self(
            id: AdminAuditEntryId::generate(),
            action: $action,
            targetType: $targetType,
            targetId: $targetId,
            targetLabel: mb_substr($targetLabel, 0, 160),
            actorId: $actorId,
            actorName: mb_substr($actorName, 0, 160),
            reason: null === $reason ? null : mb_substr($reason, 0, 500),
            occurredAt: $occurredAt,
        );
    }

    public function id(): AdminAuditEntryId
    {
        return $this->id;
    }

    public function action(): AdminAuditAction
    {
        return $this->action;
    }

    public function targetType(): AdminAuditTargetType
    {
        return $this->targetType;
    }

    public function targetId(): string
    {
        return $this->targetId;
    }

    public function targetLabel(): string
    {
        return $this->targetLabel;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }

    public function actorName(): string
    {
        return $this->actorName;
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
