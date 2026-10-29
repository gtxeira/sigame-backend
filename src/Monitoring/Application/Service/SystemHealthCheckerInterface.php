<?php

declare(strict_types=1);

namespace App\Monitoring\Application\Service;

interface SystemHealthCheckerInterface
{
    public function isDatabaseReachable(): bool;
}
