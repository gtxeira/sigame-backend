<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\GetGamificationRules\ActivityEligibilityView;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Condições para uma atividade gerar XP.')]
final readonly class ActivityEligibilityResponse
{
    public function __construct(
        #[OA\Property(description: 'Check-ins necessários para a atividade ser elegível a XP.', example: 2)]
        public int $minimumCheckIns,

        #[OA\Property(description: 'Exige atividade finalizada e não cancelada ou invalidada.', example: true)]
        public bool $requiresFinishedActivity,

        #[OA\Property(description: 'Exige presença validada.', example: true)]
        public bool $requiresValidatedAttendance,

        #[OA\Property(description: 'Exige que o evento de validação marque `xpEligible=true`.', example: true)]
        public bool $requiresXpEligibleEvent,
    ) {
    }

    public static function fromView(ActivityEligibilityView $view): self
    {
        return new self(
            minimumCheckIns: $view->minimumCheckIns,
            requiresFinishedActivity: $view->requiresFinishedActivity,
            requiresValidatedAttendance: $view->requiresValidatedAttendance,
            requiresXpEligibleEvent: $view->requiresXpEligibleEvent,
        );
    }
}
