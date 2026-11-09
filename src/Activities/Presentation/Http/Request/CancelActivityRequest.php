<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Motivo opcional do cancelamento.')]
final readonly class CancelActivityRequest
{
    public function __construct(
        #[OA\Property(nullable: true, example: 'Chuva forte no horário previsto.')]
        #[Assert\Length(min: 3, max: 500)]
        public ?string $reason = null,
    ) {
    }
}
