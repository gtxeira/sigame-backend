<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Request;

use OpenApi\Attributes as OA;

/**
 * Atualização parcial de uma atividade.
 *
 * A validação semântica fica nos Value Objects do handler, para acumular
 * todos os erros de campo em uma única resposta.
 */
#[OA\Schema(description: 'Atualização parcial de uma atividade.')]
final readonly class UpdateActivityRequest
{
    public function __construct(
        #[OA\Property(nullable: true)]
        public ?string $title = null,
        #[OA\Property(nullable: true)]
        public ?string $description = null,
        #[OA\Property(nullable: true)]
        public ?string $meetingPoint = null,
        #[OA\Property(nullable: true)]
        public ?string $route = null,
        #[OA\Property(nullable: true)]
        public ?int $estimatedDistanceMeters = null,
        #[OA\Property(description: 'Novo início em ISO 8601 com offset.', nullable: true)]
        public ?string $startsAt = null,
        #[OA\Property(description: 'Nova duração média estimada; null volta à janela padrão.', nullable: true)]
        public ?int $estimatedDurationMinutes = null,
        #[OA\Property(description: 'Motivo da remarcação.', nullable: true)]
        public ?string $reason = null,
    ) {
    }
}
