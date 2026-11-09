<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Motivo obrigatório da invalidação.')]
final readonly class InvalidateActivityRequest
{
    public function __construct(
        #[OA\Property(example: 'Atividade registrada em duplicidade.')]
        #[Assert\NotBlank(message: 'O motivo é obrigatório.')]
        #[Assert\Length(min: 3, max: 500)]
        public string $reason = '',
    ) {
    }
}
