<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\OnboardingStatus;
use App\Identity\Domain\User\ValueObject\UserLevel;
use App\Identity\Domain\User\ValueObject\UserStatus;
use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Dados públicos do usuário autenticado.')]
final readonly class CurrentUserResponse
{
    /**
     * @param list<string> $modalities
     */
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(description: 'CPF, quando definido.', example: '477.051.220-14', nullable: true)]
        public ?string $cpf,

        #[OA\Property(format: 'email', example: 'user@example.com')]
        public string $email,

        #[OA\Property(description: 'Indica se o e-mail canônico foi verificado.', example: false)]
        public bool $emailVerified,

        #[OA\Property(example: 'John Doe')]
        public string $name,

        #[OA\Property(description: 'Altura em centímetros, quando informada.', example: 178, nullable: true)]
        public ?int $heightCm,

        #[OA\Property(description: 'Peso em quilogramas, quando informado.', example: 75.5, nullable: true)]
        public ?float $weightKg,

        #[OA\Property(description: 'URL da foto de perfil, quando informada.', example: 'https://example.com/photo.jpg', nullable: true)]
        public ?string $photoUrl,

        #[OA\Property(
            description: 'Modalidades praticadas.',
            type: 'array',
            items: new OA\Items(
                type: 'string',
                enum: [Modality::WALKING->value, Modality::RUNNING->value, Modality::CYCLING->value],
            ),
            example: ['caminhada', 'corrida'],
        )]
        public array $modalities,

        #[OA\Property(
            description: 'Modalidade principal, quando definida.',
            example: 'corrida',
            nullable: true,
            enum: [Modality::WALKING->value, Modality::RUNNING->value, Modality::CYCLING->value],
        )]
        public ?string $primaryModality,

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

    public static function fromEntity(User $user, ?string $photoUrl): self
    {
        return new self(
            id: $user->id()->value(),
            cpf: $user->cpf()?->formatted(),
            email: $user->email()->value(),
            emailVerified: $user->isEmailVerified(),
            name: $user->name(),
            heightCm: $user->height()?->centimeters(),
            weightKg: $user->weight()?->kilograms(),
            photoUrl: $photoUrl,
            modalities: array_map(
                static fn (Modality $modality): string => $modality->value,
                $user->modalities(),
            ),
            primaryModality: $user->primaryModality()?->value,
            level: $user->level()->value(),
            status: $user->status()->value,
            onboardingStatus: $user->onboardingStatus()->value,
            role: $user->role()->value,
            createdAt: $user->createdAt()->toIsoStringWithMilliseconds(),
        );
    }
}
