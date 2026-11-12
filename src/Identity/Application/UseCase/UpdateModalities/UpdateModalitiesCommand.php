<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\UpdateModalities;

use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Modality;

/**
 * Alteração das modalidades de um usuário que já concluiu o onboarding.
 *
 * Não há flag de "campo presente": a coleção inteira é substituída, porque
 * modalidades não têm semântica de merge parcial.
 */
final readonly class UpdateModalitiesCommand
{
    /**
     * @param list<Modality> $modalities
     */
    public function __construct(
        private UserId $userId,
        private array $modalities,
        private ?Modality $primaryModality,
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
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
