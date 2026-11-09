<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use Doctrine\ORM\Mapping as ORM;

/**
 * Dados de perfil do usuário.
 *
 * Reúne apenas informações de apresentação e biometria; credenciais e
 * estado de acesso ficam em VOs próprios.
 */
#[ORM\Embeddable]
final readonly class Profile
{
    private function __construct(
        #[ORM\Column(name: 'name', type: 'string', length: 255)]
        private string $name,

        #[ORM\Column(name: 'height_cm', type: 'height', nullable: true)]
        private ?Height $height,

        #[ORM\Column(name: 'weight_kg', type: 'weight', nullable: true)]
        private ?Weight $weight,

        #[ORM\Column(name: 'photo_key', type: 'string', length: 255, nullable: true)]
        private ?string $photoKey,
    ) {
    }

    public static function create(string $name): self
    {
        return new self($name, null, null, null);
    }

    public function withName(string $name): self
    {
        return new self($name, $this->height, $this->weight, $this->photoKey);
    }

    public function withHeight(?Height $height): self
    {
        return new self($this->name, $height, $this->weight, $this->photoKey);
    }

    public function withWeight(?Weight $weight): self
    {
        return new self($this->name, $this->height, $weight, $this->photoKey);
    }

    public function withPhoto(?string $photoKey): self
    {
        return new self($this->name, $this->height, $this->weight, $photoKey);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function height(): ?Height
    {
        return $this->height;
    }

    public function weight(): ?Weight
    {
        return $this->weight;
    }

    public function photoKey(): ?string
    {
        return $this->photoKey;
    }
}
