<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\CompleteOnboarding;

use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Modality;

/**
 * Command para concluir o onboarding do usuário autenticado.
 *
 * O onboarding cobre apenas os dados que definem a progressão: modalidades e
 * modalidade principal. Altura, peso e foto são atualizados pelo perfil.
 */
final readonly class CompleteOnboardingCommand
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
