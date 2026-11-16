<?php

declare(strict_types=1);

namespace App\Shared\Application\Administration;

use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;

/**
 * Dados de uma ação administrativa a registrar na trilha de auditoria.
 *
 * O rótulo do alvo é um snapshot resolvido no momento da ação: a auditoria
 * continua legível mesmo depois de o grupo ser excluído ou o nome mudar.
 * O nome do ator é resolvido pelo adapter, a partir de `actorId`.
 */
final readonly class AdminAuditRecord
{
    public function __construct(
        public AdminAuditAction $action,
        public AdminAuditTargetType $targetType,
        public string $targetId,
        public string $targetLabel,
        public string $actorId,
        public ?string $reason = null,
    ) {
    }
}
