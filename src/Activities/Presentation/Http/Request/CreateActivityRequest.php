<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Request;

use OpenApi\Attributes as OA;

/**
 * Dados para criar uma atividade.
 *
 * A validação semântica fica nos Value Objects do handler, para acumular
 * todos os erros de campo em uma única resposta.
 */
#[OA\Schema(description: 'Dados para criar uma atividade.')]
final readonly class CreateActivityRequest
{
    public function __construct(
        #[OA\Property(description: 'Título da atividade.', example: 'Corrida no Parque')]
        public string $title = '',
        #[OA\Property(description: 'Descrição opcional.', nullable: true)]
        public ?string $description = null,
        #[OA\Property(description: 'Data e hora de início em ISO 8601 com offset.', example: '2026-11-15T10:00:00-03:00')]
        public string $startsAt = '',
        #[OA\Property(description: 'Duração média estimada em minutos. Quando omitida, a janela de planejamento usa 120 minutos.', nullable: true, example: 60)]
        public ?int $estimatedDurationMinutes = null,
        #[OA\Property(description: 'Ponto de encontro.', example: 'Portão principal do parque')]
        public string $meetingPoint = '',
        #[OA\Property(description: 'Ponto final ou trajeto.', example: 'Volta completa na pista externa')]
        public string $route = '',
        #[OA\Property(description: 'Distância estimada em metros.', example: 5000)]
        public int $estimatedDistanceMeters = 0,
    ) {
    }
}
