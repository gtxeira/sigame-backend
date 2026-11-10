<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\GetGamificationRules\DecayRulesView;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Regras de deleveling por inatividade.')]
final readonly class DecayRulesResponse
{
    public function __construct(
        #[OA\Property(description: 'Dias de tolerância sem atividade.', example: 7)]
        public int $graceDays,

        #[OA\Property(description: 'Intervalo entre perdas após a tolerância.', example: 2)]
        public int $periodDays,

        #[OA\Property(description: 'Percentual da barra consumido por período.', example: 2)]
        public int $barPercentPerPeriod,

        #[OA\Property(description: 'Nível mínimo alcançável pelo deleveling.', example: 2)]
        public int $floorLevel,

        #[OA\Property(description: 'Percentual da barra do novo nível ao cair.', example: 98)]
        public int $levelDropBarPercent,

        #[OA\Property(description: 'A contagem usa o instante da validação da presença.', example: true)]
        public bool $usesValidationInstant,
    ) {
    }

    public static function fromView(DecayRulesView $view): self
    {
        return new self(
            graceDays: $view->graceDays,
            periodDays: $view->periodDays,
            barPercentPerPeriod: $view->barPercentPerPeriod,
            floorLevel: $view->floorLevel,
            levelDropBarPercent: $view->levelDropBarPercent,
            usesValidationInstant: $view->usesValidationInstant,
        );
    }
}
