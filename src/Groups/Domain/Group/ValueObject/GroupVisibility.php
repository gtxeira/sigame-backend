<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

/**
 * Recorte de visibilidade da listagem de grupos.
 *
 * A busca pública usa `Visible` (padrão): grupos arquivados e excluídos ficam de
 * fora. A administração SIGAME usa os demais valores para auditoria e operação.
 */
enum GroupVisibility: string
{
    case Visible = 'visible';
    case Archived = 'archived';
    case Deleted = 'deleted';
    case All = 'all';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $visibility): string => $visibility->value, self::cases());
    }
}
