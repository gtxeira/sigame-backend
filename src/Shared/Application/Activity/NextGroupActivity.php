<?php

declare(strict_types=1);

namespace App\Shared\Application\Activity;

/**
 * Próxima atividade de um grupo (em andamento ou prevista).
 *
 * DTO do kernel usado por outros módulos — no caso, a listagem de grupos —
 * sem importar o agregado `Activity`.
 */
final readonly class NextGroupActivity
{
    public function __construct(
        public string $groupId,
        public string $activityId,
        public string $title,
        /** `scheduled` ou `started`. */
        public string $status,
        public string $startsAt,
    ) {
    }
}
