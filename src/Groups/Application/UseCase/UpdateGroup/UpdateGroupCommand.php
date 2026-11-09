<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\UpdateGroup;

use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;

final readonly class UpdateGroupCommand
{
    public function __construct(
        public GroupId $groupId,
        public MemberUserId $actorId,
        public ?string $name,
        public ?string $description,
        public bool $descriptionProvided,
        public ?string $modality,
        public ?string $communicationLink,
        public bool $communicationLinkProvided,
    ) {
    }
}
