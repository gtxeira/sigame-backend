<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Request;

use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Filtros da listagem de atividades.')]
final readonly class ActivityListQuery
{
    public function __construct(
        #[OA\Property(example: 1)]
        #[Assert\Positive]
        public int $page = 1,
        #[OA\Property(example: 20)]
        #[Assert\Range(min: 1, max: 30)]
        public int $perPage = 20,
        #[OA\Property(enum: ['scheduled', 'started', 'finished', 'cancelled', 'invalidated'], nullable: true)]
        #[Assert\Choice(callback: [ActivityStatus::class, 'values'], message: 'O estado informado é inválido.')]
        public ?string $status = null,
        #[OA\Property(description: 'Início mínimo em ISO 8601.', nullable: true)]
        public ?string $from = null,
        #[OA\Property(description: 'Início máximo em ISO 8601.', nullable: true)]
        public ?string $to = null,
        #[OA\Property(description: 'Ordem por início da atividade.', enum: ['asc', 'desc'], example: 'asc')]
        #[Assert\Choice(choices: ['asc', 'desc'], message: 'A ordem informada é inválida.')]
        public string $order = 'asc',
    ) {
    }
}
