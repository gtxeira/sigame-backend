<?php

declare(strict_types=1);

namespace App\Shared\Application\Activity;

/**
 * Porta de leitura síncrona do resumo de uma atividade.
 *
 * Notificações usam esta porta para resolver título, horário e participantes.
 * O adapter concreto vive no módulo Activities.
 */
interface ActivitySummaryReaderInterface
{
    public function summary(string $activityId): ?ActivitySummary;
}
