<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Audit;

use App\Administration\Domain\Audit\Entity\AdminAuditEntry;
use App\Administration\Domain\Audit\Repository\AdminAuditRepositoryInterface;
use App\Shared\Application\Administration\AdminAuditRecord;
use App\Shared\Application\Administration\AdminAuditRecorderInterface;
use App\Shared\Application\User\UserMemberProfileReaderInterface;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Adapter Doctrine da trilha de auditoria.
 *
 * Resolve o nome do ator no momento da escrita e persiste a entrada na mesma
 * transação do caso de uso que chamou a porta. Falha de escrita propaga: a ação
 * administrativa é desfeita junto.
 */
final readonly class DoctrineAdminAuditRecorder implements AdminAuditRecorderInterface
{
    public function __construct(
        private AdminAuditRepositoryInterface $repository,
        private UserMemberProfileReaderInterface $profiles,
    ) {
    }

    public function record(AdminAuditRecord $record): void
    {
        $profile = $this->profiles->profiles([$record->actorId])[$record->actorId] ?? null;

        $this->repository->append(AdminAuditEntry::record(
            action: $record->action,
            targetType: $record->targetType,
            targetId: $record->targetId,
            targetLabel: $record->targetLabel,
            actorId: $record->actorId,
            actorName: null === $profile ? 'Administração SIGAME' : $profile->name,
            reason: $record->reason,
            occurredAt: Instant::now(),
        ));
    }
}
