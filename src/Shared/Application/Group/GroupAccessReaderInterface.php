<?php

declare(strict_types=1);

namespace App\Shared\Application\Group;

/**
 * Porta de leitura síncrona do acesso a um grupo.
 *
 * O adapter concreto vive no módulo Groups, dono do dado. Módulos consumidores
 * importam apenas esta interface do kernel.
 */
interface GroupAccessReaderInterface
{
    /**
     * Retorna a fotografia de acesso ou `null` quando o grupo não existe.
     */
    public function snapshot(string $groupId, string $userId): ?GroupAccessSnapshot;

    /**
     * Retorna os grupos em que o usuário possui participação aprovada.
     *
     * @return list<string>
     */
    public function approvedGroupIds(string $userId): array;
}
