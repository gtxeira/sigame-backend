<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Próxima atividade de um grupo (em andamento ou prevista).')]
final readonly class GroupNextActivityResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $activityId,

        #[OA\Property(
            description: 'Título da atividade. Nulo para quem não é membro aprovado do grupo.',
            example: 'Caminhada no Parque',
            nullable: true,
        )]
        public ?string $title,

        #[OA\Property(description: 'Estado da atividade.', example: 'scheduled', enum: ['scheduled', 'started'])]
        public string $status,

        #[OA\Property(format: 'date-time', example: '2026-11-15T10:00:00.000+00:00')]
        public string $startsAt,
    ) {
    }
}
