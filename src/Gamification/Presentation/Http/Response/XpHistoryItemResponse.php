<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\ListXpHistory\XpHistoryItemView;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Lançamento do histórico de XP.')]
final readonly class XpHistoryItemResponse
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(description: 'Natureza do lançamento.', example: 'activity')]
        public string $kind,

        #[OA\Property(description: 'XP concedido ou estornado.', example: 35)]
        public int $amount,

        #[OA\Property(description: 'Descrição exibível do lançamento.', example: 'Atividade validada')]
        public string $description,

        #[OA\Property(format: 'uuid', nullable: true, example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public ?string $activityId,

        #[OA\Property(description: 'Data local da atividade.', example: '2026-11-09', nullable: true)]
        public ?string $activityDate,

        #[OA\Property(description: 'Semana de referência.', example: '2026-11-09', nullable: true)]
        public ?string $weekKey,

        #[OA\Property(description: 'Versão da regra usada.', example: '2026-11-v1')]
        public string $ruleVersion,

        #[OA\Property(type: 'object', nullable: true, additionalProperties: true)]
        public ?array $metadata,

        #[OA\Property(format: 'date-time', example: '2026-11-09T12:00:00.000+00:00')]
        public string $createdAt,

        #[OA\Property(format: 'date-time', nullable: true, example: null)]
        public ?string $reversedAt,
    ) {
    }

    public static function fromView(XpHistoryItemView $view): self
    {
        return new self(
            id: $view->id,
            kind: $view->kind,
            amount: $view->amount,
            description: self::description($view->kind),
            activityId: $view->activityId,
            activityDate: $view->activityDate,
            weekKey: $view->weekKey,
            ruleVersion: $view->ruleVersion,
            metadata: $view->metadata,
            createdAt: $view->createdAt,
            reversedAt: $view->reversedAt,
        );
    }

    private static function description(string $kind): string
    {
        return match ($kind) {
            'onboarding' => 'Onboarding concluído',
            'activity' => 'Atividade validada',
            'weekly_start' => 'Início de semana',
            'weekly_goal' => 'Meta semanal',
            'reversal' => 'Estorno',
            'inactivity_decay' => 'Perda por inatividade',
            'legacy_bonus' => 'Bônus de fidelidade',
            'backfill' => 'Progresso inicial',
            default => 'XP',
        };
    }
}
