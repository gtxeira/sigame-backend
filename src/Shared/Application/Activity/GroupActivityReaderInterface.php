<?php

declare(strict_types=1);

namespace App\Shared\Application\Activity;

use App\Shared\Domain\ValueObject\Instant;

/**
 * Porta de leitura de atividades por grupo, usada pela listagem de grupos.
 *
 * Mantém o módulo Groups sem importar o agregado `Activity`: a Home precisa da
 * próxima atividade de cada card e do filtro por data das atividades.
 */
interface GroupActivityReaderInterface
{
    /**
     * Próxima atividade (em andamento ou prevista) de cada grupo.
     *
     * @param list<string> $groupIds
     *
     * @return array<string, NextGroupActivity> indexado pelo id do grupo
     */
    public function nextForGroups(array $groupIds): array;

    /**
     * Grupos com atividade em andamento ou prevista dentro do intervalo.
     *
     * @return list<string>
     */
    public function groupIdsWithActivityInRange(Instant $from, Instant $to): array;
}
