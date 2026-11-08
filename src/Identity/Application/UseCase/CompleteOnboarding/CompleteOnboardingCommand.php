<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\CompleteOnboarding;

use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Modality;

/**
 * Command para concluir o onboarding do usuário autenticado.
 */
final readonly class CompleteOnboardingCommand
{
    /**
     * @param list<Modality> $modalities
     */
    public function __construct(
        private UserId $userId,
        private ?int $heightCm,
        private ?float $weightKg,
        private ?string $photoUrl,
        private array $modalities,
        private ?Modality $primaryModality,
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function heightCm(): ?int
    {
        return $this->heightCm;
    }

    public function weightKg(): ?float
    {
        return $this->weightKg;
    }

    public function photoUrl(): ?string
    {
        return $this->photoUrl;
    }

    /**
     * @return list<Modality>
     */
    public function modalities(): array
    {
        return $this->modalities;
    }

    public function primaryModality(): ?Modality
    {
        return $this->primaryModality;
    }
}
