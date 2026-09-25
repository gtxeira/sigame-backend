<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Service;

interface SystemHealthCheckerInterface
{
    public function isDatabaseReachable(): bool;
}
