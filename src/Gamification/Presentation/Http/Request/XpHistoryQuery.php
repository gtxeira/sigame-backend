<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Paginação do histórico de XP.')]
final readonly class XpHistoryQuery
{
    public function __construct(
        #[OA\Property(description: 'Página, a partir de 1.', example: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Itens por página, no máximo 30.', example: 20)]
        #[Assert\Range(min: 1, max: 30)]
        public int $perPage = 20,
    ) {
    }
}
