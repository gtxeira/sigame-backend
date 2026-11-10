<?php

declare(strict_types=1);

namespace App\Shared\Application\Activity;

use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Domain\ValueObject\Modality;

/**
 * Contexto mínimo de uma atividade para o cálculo de XP.
 *
 * O módulo Gamification consome esta fotografia em vez de importar o
 * agregado Activity. A modalidade pertence ao grupo e é resolvida por uma
 * porta própria.
 */
final readonly class ActivityXpContext
{
    public function __construct(
        public string $activityId,
        public string $groupId,
        public Modality $modality,
        public int $distanceMeters,
        public ?int $durationMinutes,
        public Instant $startsAt,
        public int $checkedInParticipants,
    ) {
    }
}
