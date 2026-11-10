<?php

declare(strict_types=1);

namespace App\Shared\Application\Activity;

/**
 * Porta de leitura síncrona do contexto de XP de uma atividade.
 *
 * O adapter concreto vive no módulo Activities, dono do agregado.
 */
interface ActivityXpReaderInterface
{
    /**
     * Retorna o contexto ou `null` quando a atividade não existe.
     */
    public function contextOf(string $activityId): ?ActivityXpContext;
}
