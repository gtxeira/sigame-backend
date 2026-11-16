<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Ações globais registradas na trilha de auditoria da administração.
 *
 * O valor é contrato público com o cliente da API (filtro de `GET /admin/audit`):
 * alterá-lo é breaking change; renomear o caso não é.
 *
 * Só entram ações executadas por `ADMIN_SIGAME`. Ações de administradores de
 * grupo seguem em eventos de domínio e notificações, sem compor a auditoria
 * global (ADR-048).
 */
enum AdminAuditAction: string
{
    case AccountSuspended = 'ACCOUNT_SUSPENDED';
    case AccountReactivated = 'ACCOUNT_REACTIVATED';
    case GroupArchived = 'GROUP_ARCHIVED';
    case GroupUnarchived = 'GROUP_UNARCHIVED';
    case GroupDeleted = 'GROUP_DELETED';
    case ActivityCancelled = 'ACTIVITY_CANCELLED';
    case ActivityInvalidated = 'ACTIVITY_INVALIDATED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
