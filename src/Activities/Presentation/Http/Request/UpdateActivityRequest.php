<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Atualização parcial de uma atividade.')]
final readonly class UpdateActivityRequest
{
    public function __construct(
        #[OA\Property(nullable: true)]
        #[Assert\Length(min: 3, max: 100)]
        public ?string $title = null,
        #[OA\Property(nullable: true)]
        #[Assert\Length(max: 500)]
        public ?string $description = null,
        #[OA\Property(nullable: true)]
        #[Assert\Length(min: 3, max: 255)]
        public ?string $meetingPoint = null,
        #[OA\Property(nullable: true)]
        #[Assert\Length(min: 3, max: 500)]
        public ?string $route = null,
        #[OA\Property(nullable: true)]
        #[Assert\Range(min: 1, max: 1000000)]
        public ?int $estimatedDistanceMeters = null,
        #[OA\Property(description: 'Novo início em ISO 8601 com offset.', nullable: true)]
        public ?string $startsAt = null,
        #[OA\Property(description: 'Nova duração média estimada; null volta à janela padrão.', nullable: true)]
        #[Assert\Range(min: 15, max: 1440)]
        public ?int $estimatedDurationMinutes = null,
        #[OA\Property(description: 'Motivo da remarcação.', nullable: true)]
        #[Assert\Length(min: 3, max: 500)]
        public ?string $reason = null,
    ) {
    }
}
