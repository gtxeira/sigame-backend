<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Referência local ao grupo dono da atividade.
 *
 * Activities não importa o GroupId de Groups; a validação e a autorização
 * usam a porta síncrona do Shared.
 */
final readonly class ActivityGroupId extends BaseId
{
}
