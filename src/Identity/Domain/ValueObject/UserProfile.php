<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\OnboardingInvalidPrimaryModalityException;

/**
 * Value Object para dados opcionais do perfil do usuário.
 *
 * Peso, altura e foto são opcionais (RN01.5, RN01.6, RN01.7).
 */
final readonly class UserProfile
{
    /**
     * @param list<string> $modalities
     */
    private function __construct(
        private ?float $weight,
        private ?float $height,
        private ?string $photoUrl,
        private array $modalities,
        private ?string $primaryModality,
    ) {
    }

    /**
     * @param list<string> $modalities
     */
    public static function create(
        ?float $weight = null,
        ?float $height = null,
        ?string $photoUrl = null,
        array $modalities = [],
        ?string $primaryModality = null,
    ): self {
        if (null !== $primaryModality && !in_array($primaryModality, $modalities, true)) {
            throw new OnboardingInvalidPrimaryModalityException();
        }

        return new self($weight, $height, $photoUrl, $modalities, $primaryModality);
    }

    public function weight(): ?float
    {
        return $this->weight;
    }

    public function height(): ?float
    {
        return $this->height;
    }

    public function photoUrl(): ?string
    {
        return $this->photoUrl;
    }

    /**
     * @return list<string>
     */
    public function modalities(): array
    {
        return $this->modalities;
    }

    public function primaryModality(): ?string
    {
        return $this->primaryModality;
    }

    public function hasModality(string $modality): bool
    {
        return in_array($modality, $this->modalities, true);
    }

    public function equals(self $other): bool
    {
        return $this->weight === $other->weight
            && $this->height === $other->height
            && $this->photoUrl === $other->photoUrl
            && $this->modalities === $other->modalities
            && $this->primaryModality === $other->primaryModality;
    }
}
