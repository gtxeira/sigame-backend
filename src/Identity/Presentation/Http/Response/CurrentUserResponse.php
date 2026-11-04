<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\OnboardingStatus;
use App\Identity\Domain\User\ValueObject\UserLevel;
use App\Identity\Domain\User\ValueObject\UserStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Dados públicos do usuário autenticado.')]
final readonly class CurrentUserResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(description: 'CPF, quando definido.', nullable: true, example: '477.051.220-14')]
        public ?string $cpf,

        #[OA\Property(format: 'email', nullable: true, example: 'user@example.com')]
        public ?string $email,

        #[OA\Property(example: 'John Doe')]
        public string $name,

        #[OA\Property(example: UserLevel::MIN_LEVEL)]
        public int $level,

        #[OA\Property(example: UserStatus::ACTIVE, enum: UserStatus::class)]
        public string $status,

        #[OA\Property(example: OnboardingStatus::PENDING, enum: OnboardingStatus::class)]
        public string $onboardingStatus,

        #[OA\Property(description: 'Papel do usuário.', example: 'USER')]
        public string $role,

        #[OA\Property(format: 'date-time', example: '2026-09-21T10:00:00.000+00:00')]
        public string $createdAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->id()->value(),
            cpf: $user->cpf()?->formatted(),
            email: $user->email()?->value(),
            name: $user->name(),
            level: $user->level()->value(),
            status: $user->status()->value,
            onboardingStatus: $user->onboardingStatus()->value,
            role: $user->role()->value,
            createdAt: $user->createdAt()->toIsoStringWithMilliseconds(),
        );
    }
}
