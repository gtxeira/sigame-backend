<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Response;

use App\Gamification\Application\UseCase\GetGamificationRules\XpSourceView;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Fonte positiva de XP.')]
final readonly class XpSourceResponse
{
    public function __construct(
        #[OA\Property(description: 'Natureza do lançamento.', example: 'onboarding')]
        public string $kind,

        #[OA\Property(description: 'Rótulo exibível.', example: 'Onboarding concluído')]
        public string $label,

        #[OA\Property(description: 'Descrição exibível da fonte.', example: 'Concedido uma única vez na conclusão do onboarding.')]
        public string $description,

        #[OA\Property(description: 'Valor fixo; nulo quando a fonte é uma faixa.', nullable: true, example: 100)]
        public ?int $amount,

        #[OA\Property(description: 'Valor mínimo quando a fonte é uma faixa.', nullable: true, example: null)]
        public ?int $minAmount,

        #[OA\Property(description: 'Valor máximo quando a fonte é uma faixa.', nullable: true, example: null)]
        public ?int $maxAmount,
    ) {
    }

    public static function fromView(XpSourceView $view): self
    {
        return new self(
            kind: $view->kind,
            label: $view->label,
            description: $view->description,
            amount: $view->amount,
            minAmount: $view->minAmount,
            maxAmount: $view->maxAmount,
        );
    }
}
