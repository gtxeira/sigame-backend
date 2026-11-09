<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Dados para criar uma atividade.')]
final readonly class CreateActivityRequest
{
    public function __construct(
        #[OA\Property(description: 'Título da atividade.', example: 'Corrida no Parque')]
        #[Assert\NotBlank(message: 'O título é obrigatório.')]
        #[Assert\Length(min: 3, max: 100, minMessage: 'O título deve ter no mínimo {{ limit }} caracteres.', maxMessage: 'O título deve ter no máximo {{ limit }} caracteres.')]
        public string $title = '',
        #[OA\Property(description: 'Descrição opcional.', nullable: true)]
        #[Assert\Length(max: 500)]
        public ?string $description = null,
        #[OA\Property(description: 'Data e hora de início em ISO 8601 com offset.', example: '2026-11-15T10:00:00-03:00')]
        #[Assert\NotBlank(message: 'O início é obrigatório.')]
        public string $startsAt = '',
        #[OA\Property(description: 'Duração média estimada em minutos. Quando omitida, a janela de planejamento usa 120 minutos.', nullable: true, example: 60)]
        #[Assert\Range(min: 15, max: 1440, notInRangeMessage: 'A duração deve estar entre {{ min }} e {{ max }} minutos.')]
        public ?int $estimatedDurationMinutes = null,
        #[OA\Property(description: 'Ponto de encontro.', example: 'Portão principal do parque')]
        #[Assert\NotBlank(message: 'O ponto de encontro é obrigatório.')]
        #[Assert\Length(min: 3, max: 255)]
        public string $meetingPoint = '',
        #[OA\Property(description: 'Ponto final ou trajeto.', example: 'Volta completa na pista externa')]
        #[Assert\NotBlank(message: 'O trajeto é obrigatório.')]
        #[Assert\Length(min: 3, max: 500)]
        public string $route = '',
        #[OA\Property(description: 'Distância estimada em metros.', example: 5000)]
        #[Assert\Positive(message: 'A distância deve ser positiva.')]
        #[Assert\Range(min: 1, max: 1000000)]
        public int $estimatedDistanceMeters = 0,
    ) {
    }
}
