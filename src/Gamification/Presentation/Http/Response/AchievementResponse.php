<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\ListAchievements\AchievementView;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Conquista desbloqueada pelo usuário autenticado.')]
final readonly class AchievementResponse
{
    public function __construct(
        #[OA\Property(example: AchievementCode::LENDA->value, enum: [AchievementCode::LENDA->value])]
        public string $code,

        #[OA\Property(example: 'Lenda')]
        public string $name,

        #[OA\Property(example: 'Alcance o nível 10.')]
        public string $description,

        #[OA\Property(format: 'date-time', example: '2026-11-12T10:00:00.000+00:00')]
        public string $achievedAt,

        #[OA\Property(
            description: 'Data em que a comemoração foi exibida; nula enquanto não celebrada.',
            format: 'date-time',
            example: null,
            nullable: true,
        )]
        public ?string $celebratedAt,
    ) {
    }

    public static function fromView(AchievementView $view): self
    {
        return new self(
            code: $view->code->value,
            name: $view->code->label(),
            description: $view->code->description(),
            achievedAt: $view->achievedAt->toIsoStringWithMilliseconds(),
            celebratedAt: $view->celebratedAt?->toIsoStringWithMilliseconds(),
        );
    }
}
