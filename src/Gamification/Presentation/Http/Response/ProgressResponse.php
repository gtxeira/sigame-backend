<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\GetMyProgress\MyProgressView;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Progresso de XP e ofensiva semanal do usuário autenticado.')]
final readonly class ProgressResponse
{
    public function __construct(
        #[OA\Property(example: 4)]
        public int $level,

        #[OA\Property(description: 'Saldo de XP usado para o nível.', example: 700)]
        public int $xpBalance,

        #[OA\Property(description: 'XP vitalício, preservado mesmo com deleveling.', example: 820)]
        public int $xpLifetime,

        #[OA\Property(description: 'XP acumulado no início do nível atual.', example: 600)]
        public int $levelStartXp,

        #[OA\Property(description: 'XP necessário para o próximo nível; nulo no nível máximo.', example: 1000, nullable: true)]
        public ?int $nextLevelXp,

        #[OA\Property(description: 'XP restante para o próximo nível; nulo no nível máximo.', example: 300, nullable: true)]
        public ?int $xpToNextLevel,

        #[OA\Property(description: 'Percentual da barra do nível, de 0 a 100.', example: 25)]
        public int $barPercent,

        #[OA\Property(description: 'Semanas consecutivas com pelo menos uma atividade.', example: 1)]
        public int $currentWeeklyStreak,

        #[OA\Property(description: 'Recorde de semanas consecutivas.', example: 4)]
        public int $bestWeeklyStreak,

        #[OA\Property(description: 'Meta de atividades da semana.', example: 2)]
        public int $weeklyGoal,

        #[OA\Property(description: 'Atividades válidas na semana corrente.', example: 1)]
        public int $weeklyActivities,

        #[OA\Property(description: 'Segunda-feira que identifica a semana.', example: '2026-11-09')]
        public string $weekKey,

        #[OA\Property(description: 'Primeiro dia da semana.', example: '2026-11-09')]
        public string $weekStart,

        #[OA\Property(description: 'Último dia da semana.', example: '2026-11-15')]
        public string $weekEnd,

        #[OA\Property(
            description: 'Estado do foguinho semanal.',
            example: 'ACESO',
            enum: ['APAGADO', 'ACESO', 'EM_RISCO', 'COMPLETO'],
        )]
        public string $streakState,

        #[OA\Property(description: 'Versão das regras de XP.', example: '2026-11-v1')]
        public string $ruleVersion,
    ) {
    }

    public static function fromView(MyProgressView $view): self
    {
        return new self(
            level: $view->level,
            xpBalance: $view->xpBalance,
            xpLifetime: $view->xpLifetime,
            levelStartXp: $view->levelStartXp,
            nextLevelXp: $view->nextLevelXp,
            xpToNextLevel: $view->xpToNextLevel,
            barPercent: (int) round($view->barPercent * 100),
            currentWeeklyStreak: $view->currentWeeklyStreak,
            bestWeeklyStreak: $view->bestWeeklyStreak,
            weeklyGoal: $view->weeklyGoal,
            weeklyActivities: $view->weeklyActivities,
            weekKey: $view->weekKey,
            weekStart: $view->weekStart,
            weekEnd: $view->weekEnd,
            streakState: $view->state->value,
            ruleVersion: $view->ruleVersion,
        );
    }
}
