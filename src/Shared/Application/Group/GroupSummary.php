<?php

declare(strict_types=1);

namespace App\Shared\Application\Group;

/**
 * Resumo síncrono de um grupo para composição de notificações.
 */
final readonly class GroupSummary
{
    /**
     * @param list<string> $adminIds
     * @param list<string> $approvedMemberIds
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $adminIds,
        public ?string $supervisorId,
        public array $approvedMemberIds,
        public ?string $ownerId = null,
    ) {
    }
}
