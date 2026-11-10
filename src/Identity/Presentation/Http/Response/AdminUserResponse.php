<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\OnboardingStatus;
use App\Identity\Domain\User\ValueObject\UserStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Dados administrativos de uma conta, sem dados sensíveis de perfil.')]
final readonly class AdminUserResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(example: 'John Doe')]
        public string $name,

        #[OA\Property(format: 'email', example: 'user@example.com')]
        public string $email,

        #[OA\Property(description: 'Indica se o e-mail canônico foi verificado.', example: true)]
        public bool $emailVerified,

        #[OA\Property(example: UserStatus::ACTIVE->value, enum: UserStatus::class)]
        public string $status,

        #[OA\Property(example: 'USER')]
        public string $role,

        #[OA\Property(example: 2)]
        public int $level,

        #[OA\Property(example: OnboardingStatus::COMPLETED->value, enum: OnboardingStatus::class)]
        public string $onboardingStatus,

        #[OA\Property(format: 'date-time', example: '2026-09-21T10:00:00.000+00:00')]
        public string $createdAt,

        #[OA\Property(format: 'date-time', example: '2026-09-21T10:00:00.000+00:00')]
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->id()->value(),
            name: $user->name(),
            email: $user->email()->value(),
            emailVerified: $user->isEmailVerified(),
            status: $user->status()->value,
            role: $user->role()->value,
            level: $user->level()->value(),
            onboardingStatus: $user->onboardingStatus()->value,
            createdAt: $user->createdAt()->toIsoStringWithMilliseconds(),
            updatedAt: $user->updatedAt()->toIsoStringWithMilliseconds(),
        );
    }
}
