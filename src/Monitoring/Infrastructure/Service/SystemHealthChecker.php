<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Service;

use Doctrine\DBAL\Connection;
use Throwable;

final readonly class SystemHealthChecker implements SystemHealthCheckerInterface
{
    public function __construct(
        private Connection $databaseConnection,
    ) {
    }

    public function isDatabaseReachable(): bool
    {
        try {
            $this->databaseConnection->executeQuery('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
