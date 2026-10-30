<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Entity;

use App\Shared\Domain\ValueObject\Modality;
use Doctrine\ORM\Mapping as ORM;

/**
 * Modalidade praticada por um usuário.
 *
 * A chave primária composta (user_id, modality) impede modalidades duplicadas.
 * O índice único parcial garante no máximo uma modalidade principal por usuário.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_modalities')]
#[ORM\UniqueConstraint(
    name: 'uniq_user_modalities_primary',
    fields: ['user'],
    options: ['where' => 'is_primary'],
)]
#[ORM\Index(name: 'idx_user_modalities_modality', fields: ['modality'])]
final class UserModality
{
    private function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'modalities')]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        private readonly User $user,

        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 20, enumType: Modality::class)]
        private readonly Modality $modality,

        #[ORM\Column(name: 'is_primary', type: 'boolean', options: ['default' => false])]
        private bool $isPrimary,
    ) {
    }

    public static function create(
        User $user,
        Modality $modality,
        bool $isPrimary = false,
    ): self {
        return new self($user, $modality, $isPrimary);
    }

    public function user(): User
    {
        return $this->user;
    }

    public function modality(): Modality
    {
        return $this->modality;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }
}
