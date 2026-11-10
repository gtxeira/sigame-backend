<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\GetGamificationRules\WeeklyRulesView;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Regras da ofensiva e do bônus semanal.')]
final readonly class WeeklyRulesResponse
{
    /**
     * @param list<array{streak: int, amount: int}> $goalBonusByStreak
     */
    public function __construct(
        #[OA\Property(description: 'Atividades válidas necessárias para completar a meta semanal.', example: 2)]
        public int $goalActivities,

        #[OA\Property(description: 'XP da primeira atividade válida da semana.', example: 10)]
        public int $startXp,

        #[OA\Property(description: 'XP base do bônus de meta na primeira semana.', example: 20)]
        public int $goalBaseXp,

        #[OA\Property(description: 'Acréscimo por semana consecutiva.', example: 5)]
        public int $goalStepXp,

        #[OA\Property(description: 'Teto do bônus de meta.', example: 50)]
        public int $goalMaxXp,

        #[OA\Property(
            description: 'Bônus de meta por semana consecutiva. A partir de `goalBonusCapFromStreak`, o valor é o máximo.',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'streak', type: 'integer', example: 1),
                    new OA\Property(property: 'amount', type: 'integer', example: 20),
                ],
            ),
        )]
        public array $goalBonusByStreak,

        #[OA\Property(description: 'Primeira sequência em que o bônus atinge o valor máximo.', example: 7)]
        public int $goalBonusCapFromStreak,

        #[OA\Property(description: 'Primeiro dia da semana.', example: 'monday')]
        public string $weekStartsOn,
    ) {
    }

    public static function fromView(WeeklyRulesView $view): self
    {
        return new self(
            goalActivities: $view->goalActivities,
            startXp: $view->startXp,
            goalBaseXp: $view->goalBaseXp,
            goalStepXp: $view->goalStepXp,
            goalMaxXp: $view->goalMaxXp,
            goalBonusByStreak: $view->goalBonusByStreak,
            goalBonusCapFromStreak: $view->goalBonusCapFromStreak,
            weekStartsOn: $view->weekStartsOn,
        );
    }
}
