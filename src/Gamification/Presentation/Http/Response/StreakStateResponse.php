<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\GetGamificationRules\StreakStateView;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Estado visual do foguinho semanal.')]
final readonly class StreakStateResponse
{
    public function __construct(
        #[OA\Property(description: 'Valor retornado em `streakState` no progresso.', example: 'APAGADO')]
        public string $value,

        #[OA\Property(description: 'Rótulo exibível.', example: 'Foguinho apagado')]
        public string $label,

        #[OA\Property(description: 'Descrição exibível do estado.', example: 'O usuário nunca teve uma semana válida ou a última semana terminou sem atividades.')]
        public string $description,
    ) {
    }

    public static function fromView(StreakStateView $view): self
    {
        return new self(
            value: $view->value,
            label: $view->label,
            description: $view->description,
        );
    }
}
