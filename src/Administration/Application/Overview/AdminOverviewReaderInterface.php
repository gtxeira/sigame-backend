<?php

declare(strict_types=1);

namespace App\Administration\Application\Overview;

/**
 * Porta de leitura dos contadores da visão geral.
 *
 * O adapter concreto consulta as tabelas dos módulos donos em modo leitura
 * (read model). Não há escrita nem import de entidades de outros módulos.
 */
interface AdminOverviewReaderInterface
{
    public function overview(): AdminOverview;
}
