<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Repository;

use App\Activities\Domain\Activity\Entity\Activity;

final readonly class ActivityPage
{
    /**
     * @param list<Activity> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
