<?php

declare(strict_types=1);

namespace App\Shared\Application\Group;

/**
 * Fotografia síncrona do acesso de um usuário a um grupo.
 *
 * Módulos que dependem do estado do grupo (por exemplo, Activities) usam esta
 * leitura em vez de importar classes concretas do módulo Groups.
 */
final readonly class GroupAccessSnapshot
{
    public function __construct(
        public bool $acceptsActivities,
        public bool $isApprovedMember,
        public bool $isAdmin,
        public bool $isSupervisor,
    ) {
    }

    public function canManageActivities(): bool
    {
        return $this->isAdmin || $this->isSupervisor;
    }
}
