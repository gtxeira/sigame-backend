<?php

declare(strict_types=1);

namespace App\Shared\Application\Group;

/**
 * Porta de leitura síncrona do resumo de um grupo.
 *
 * Notificações usam esta porta para resolver administradores, supervisor e
 * membros aprovados sem importar o agregado Group. O adapter concreto vive no
 * módulo Groups.
 */
interface GroupSummaryReaderInterface
{
    public function summary(string $groupId): ?GroupSummary;

    /**
     * Resolve os nomes de vários grupos de uma vez (read model de listagens).
     *
     * @param list<string> $groupIds
     *
     * @return array<string, string> indexado pelo id do grupo
     */
    public function names(array $groupIds): array;
}
