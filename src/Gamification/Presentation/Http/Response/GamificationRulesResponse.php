<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\GetGamificationRules\GamificationRulesView;
use App\Gamification\Application\UseCase\GetGamificationRules\StreakStateView;
use App\Gamification\Application\UseCase\GetGamificationRules\XpSourceView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Catálogo de regras de gamificação da versão vigente.')]
final readonly class GamificationRulesResponse
{
    /**
     * @param list<XpSourceResponse>    $xpSources
     * @param list<StreakStateResponse> $streakStates
     */
    public function __construct(
        #[OA\Property(description: 'Versão das regras.', example: '2026-11-v1')]
        public string $ruleVersion,

        #[OA\Property(description: 'Fuso usado nas datas.', example: 'America/Sao_Paulo')]
        public string $timezone,

        #[OA\Property(ref: new Model(type: LevelsRulesResponse::class))]
        public LevelsRulesResponse $levels,

        #[OA\Property(
            description: 'Fontes positivas de XP.',
            type: 'array',
            items: new OA\Items(ref: new Model(type: XpSourceResponse::class)),
        )]
        public array $xpSources,

        #[OA\Property(ref: new Model(type: ActivityXpRulesResponse::class))]
        public ActivityXpRulesResponse $activity,

        #[OA\Property(ref: new Model(type: WeeklyRulesResponse::class))]
        public WeeklyRulesResponse $weekly,

        #[OA\Property(ref: new Model(type: DecayRulesResponse::class))]
        public DecayRulesResponse $decay,

        #[OA\Property(
            description: 'Estados possíveis do foguinho semanal.',
            type: 'array',
            items: new OA\Items(ref: new Model(type: StreakStateResponse::class)),
        )]
        public array $streakStates,
    ) {
    }

    public static function fromView(GamificationRulesView $view): self
    {
        return new self(
            ruleVersion: $view->ruleVersion,
            timezone: $view->timezone,
            levels: LevelsRulesResponse::fromView($view->levels),
            xpSources: array_map(
                static fn (XpSourceView $source): XpSourceResponse => XpSourceResponse::fromView($source),
                $view->xpSources,
            ),
            activity: ActivityXpRulesResponse::fromView($view->activity),
            weekly: WeeklyRulesResponse::fromView($view->weekly),
            decay: DecayRulesResponse::fromView($view->decay),
            streakStates: array_map(
                static fn (StreakStateView $state): StreakStateResponse => StreakStateResponse::fromView($state),
                $view->streakStates,
            ),
        );
    }
}
