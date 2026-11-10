<?php

declare(strict_types=1);

namespace App\Shared\Application\Group;

use App\Shared\Domain\ValueObject\Modality;

/**
 * Porta de leitura síncrona da modalidade de um grupo.
 *
 * A modalidade é usada pelo cálculo de XP e permanece no agregado Group;
 * módulos consumidores importam apenas esta interface do kernel.
 */
interface GroupModalityReaderInterface
{
    public function modalityOf(string $groupId): ?Modality;
}
