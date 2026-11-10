<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\GetGamificationRules\ActivityXpRulesView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Fórmula e faixas do XP de atividade.')]
final readonly class ActivityXpRulesResponse
{
    /**
     * @param list<array{modality: string, xp: int}>                                              $baseXpByModality
     * @param list<array{modality: string, bands: list<array{upToMeters: int|null, bonus: int}>}> $distanceBands
     * @param list<array{minMinutes: int, maxMinutes: int|null, bonus: int}>                      $durationBands
     * @param list<array{minCheckIns: int, maxCheckIns: int|null, bonus: int}>                    $checkInBands
     * @param list<array{minActivities: int, maxActivities: int|null, percent: int}>              $dailyFactorBands
     */
    public function __construct(
        #[OA\Property(ref: new Model(type: ActivityEligibilityResponse::class))]
        public ActivityEligibilityResponse $eligibility,

        #[OA\Property(description: 'Teto de XP por atividade.', example: 100)]
        public int $maxXpPerActivity,

        #[OA\Property(description: 'Multiplicador aplicado a atividades iniciadas no fim de semana.', example: 120)]
        public int $weekendMultiplierPercent,

        #[OA\Property(description: 'Bônus de duração quando a duração não é informada.', example: 0)]
        public int $durationBonusWhenMissing,

        #[OA\Property(
            description: 'XP base por modalidade.',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'modality', type: 'string', example: 'caminhada'),
                    new OA\Property(property: 'xp', type: 'integer', example: 20),
                ],
            ),
        )]
        public array $baseXpByModality,

        #[OA\Property(
            description: 'Faixas de bônus por distância, em metros. `upToMeters` nulo significa acima da faixa anterior.',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'modality', type: 'string', example: 'caminhada'),
                    new OA\Property(
                        property: 'bands',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'upToMeters', type: 'integer', nullable: true, example: 2000),
                                new OA\Property(property: 'bonus', type: 'integer', example: 0),
                            ],
                        ),
                    ),
                ],
            ),
        )]
        public array $distanceBands,

        #[OA\Property(
            description: 'Faixas de bônus por duração.',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'minMinutes', type: 'integer', example: 30),
                    new OA\Property(property: 'maxMinutes', type: 'integer', nullable: true, example: 59),
                    new OA\Property(property: 'bonus', type: 'integer', example: 5),
                ],
            ),
        )]
        public array $durationBands,

        #[OA\Property(
            description: 'Faixas de bônus por número de check-ins.',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'minCheckIns', type: 'integer', example: 3),
                    new OA\Property(property: 'maxCheckIns', type: 'integer', nullable: true, example: 4),
                    new OA\Property(property: 'bonus', type: 'integer', example: 10),
                ],
            ),
        )]
        public array $checkInBands,

        #[OA\Property(
            description: 'Fator aplicado à enésima atividade XP-elegível do dia.',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'minActivities', type: 'integer', example: 1),
                    new OA\Property(property: 'maxActivities', type: 'integer', nullable: true, example: 3),
                    new OA\Property(property: 'percent', type: 'integer', example: 100),
                ],
            ),
        )]
        public array $dailyFactorBands,
    ) {
    }

    public static function fromView(ActivityXpRulesView $view): self
    {
        return new self(
            eligibility: ActivityEligibilityResponse::fromView($view->eligibility),
            maxXpPerActivity: $view->maxXpPerActivity,
            weekendMultiplierPercent: $view->weekendMultiplierPercent,
            durationBonusWhenMissing: $view->durationBonusWhenMissing,
            baseXpByModality: $view->baseXpByModality,
            distanceBands: $view->distanceBands,
            durationBands: $view->durationBands,
            checkInBands: $view->checkInBands,
            dailyFactorBands: $view->dailyFactorBands,
        );
    }
}
