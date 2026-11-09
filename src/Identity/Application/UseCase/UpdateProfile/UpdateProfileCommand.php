<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\UpdateProfile;

use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Atualização parcial do perfil autenticado.
 *
 * Cada flag indica se o campo estava presente no corpo da requisição. Um
 * campo ausente não é alterado; um campo presente com `null` limpa o valor
 * (somente altura e peso).
 */
final readonly class UpdateProfileCommand
{
    public function __construct(
        private UserId $userId,
        private bool $nameProvided,
        private ?string $name,
        private bool $heightProvided,
        private ?int $heightCm,
        private bool $weightProvided,
        private ?float $weightKg,
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function nameProvided(): bool
    {
        return $this->nameProvided;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function heightProvided(): bool
    {
        return $this->heightProvided;
    }

    public function heightCm(): ?int
    {
        return $this->heightCm;
    }

    public function weightProvided(): bool
    {
        return $this->weightProvided;
    }

    public function weightKg(): ?float
    {
        return $this->weightKg;
    }

    public function hasAnyChange(): bool
    {
        return $this->nameProvided || $this->heightProvided || $this->weightProvided;
    }
}
