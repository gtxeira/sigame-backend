<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Request;

use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filtros e paginação da listagem administrativa de atividades.
 */
#[OA\Schema(description: 'Filtros da listagem administrativa de atividades.')]
final readonly class AdminActivityListQuery
{
    public function __construct(
        #[OA\Property(description: 'Página, a partir de 1.', example: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Itens por página, no máximo 30.', example: 20)]
        #[Assert\Range(min: 1, max: 30)]
        public int $perPage = 20,

        #[OA\Property(description: 'Filtra pelo estado da atividade.', nullable: true, enum: ActivityStatus::class)]
        #[Assert\Choice(callback: [ActivityStatus::class, 'values'], message: 'O estado informado é inválido.')]
        public ?string $status = null,

        #[OA\Property(description: 'Filtra pelo grupo.', format: 'uuid', nullable: true)]
        #[Assert\Uuid(message: 'O grupo informado é inválido.')]
        public ?string $groupId = null,

        #[OA\Property(description: 'Busca parcial pelo título.', example: 'parque', nullable: true)]
        #[Assert\Length(max: 100)]
        public ?string $title = null,

        #[OA\Property(description: 'Início mínimo em ISO 8601.', nullable: true)]
        public ?string $from = null,

        #[OA\Property(description: 'Início máximo em ISO 8601.', nullable: true)]
        public ?string $to = null,

        #[OA\Property(description: 'Ordem por início da atividade.', enum: ['asc', 'desc'], example: 'desc')]
        #[Assert\Choice(choices: ['asc', 'desc'], message: 'A ordem informada é inválida.')]
        public string $order = 'desc',
    ) {
    }
}
