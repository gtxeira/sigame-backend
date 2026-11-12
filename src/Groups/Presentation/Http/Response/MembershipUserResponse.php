<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use App\Shared\Application\User\UserMemberProfile;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Dados públicos do usuário de uma participação.')]
final readonly class MembershipUserResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $userId,

        #[OA\Property(example: 'Maria')]
        public string $firstName,

        #[OA\Property(example: 4)]
        public int $level,

        #[OA\Property(example: null, nullable: true)]
        public ?string $photoUrl,
    ) {
    }

    public static function fromProfile(UserMemberProfile $profile): self
    {
        $parts = preg_split('/\s+/', trim($profile->name)) ?: [];

        return new self(
            userId: $profile->id,
            firstName: $parts[0] ?? $profile->name,
            level: $profile->level,
            photoUrl: $profile->photoUrl,
        );
    }
}
