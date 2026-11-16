<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Tipo do alvo de uma entrada da trilha de auditoria.
 *
 * O alvo é identificado por tipo + id; não há FK, porque o registro precisa
 * sobreviver à exclusão lógica do recurso.
 */
enum AdminAuditTargetType: string
{
    case User = 'USER';
    case Group = 'GROUP';
    case Activity = 'ACTIVITY';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
