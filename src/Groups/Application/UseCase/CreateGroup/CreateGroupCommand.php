<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\CreateGroup;

use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Domain\ValueObject\Modality;

final readonly class CreateGroupCommand
{
    public function __construct(
        public MemberUserId $actorId,
        public string $name,
        public ?string $description,
        public Modality $modality,
        public ?string $communicationLink,
        public ?int $maxMembers = null,
    ) {
    }
}
