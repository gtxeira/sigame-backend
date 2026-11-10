<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\GetGamificationRules\LevelsRulesView;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Curva de níveis da gamificação.')]
final readonly class LevelsRulesResponse
{
    /**
     * @param list<array{level: int, thresholdXp: int, xpForNextLevel: int|null}> $curve
     */
    public function __construct(
        #[OA\Property(description: 'Primeiro nível.', example: 1)]
        public int $minLevel,

        #[OA\Property(description: 'Último nível.', example: 10)]
        public int $maxLevel,

        #[OA\Property(
            description: 'Curva completa, do nível mínimo ao máximo. `xpForNextLevel` é nulo no nível máximo.',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'level', type: 'integer', example: 1),
                    new OA\Property(property: 'thresholdXp', type: 'integer', example: 0),
                    new OA\Property(property: 'xpForNextLevel', type: 'integer', nullable: true, example: 100),
                ],
            ),
        )]
        public array $curve,
    ) {
    }

    public static function fromView(LevelsRulesView $view): self
    {
        return new self(
            minLevel: $view->minLevel,
            maxLevel: $view->maxLevel,
            curve: $view->curve,
        );
    }
}
