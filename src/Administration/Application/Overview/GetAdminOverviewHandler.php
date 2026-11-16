<?php

declare(strict_types=1);

namespace App\Administration\Application\Overview;

use App\Shared\Domain\Functional\Result;

/**
 * Monta os contadores da visão geral da administração.
 */
final readonly class GetAdminOverviewHandler
{
    public function __construct(
        private AdminOverviewReaderInterface $reader,
    ) {
    }

    /**
     * @return Result<AdminOverview>
     */
    public function __invoke(): Result
    {
        return Result::success($this->reader->overview());
    }
}
