<?php

declare(strict_types=1);

namespace App\Notifications\Presentation\Http\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Contagem de notificações não lidas.')]
final readonly class UnreadCountResponse
{
    public function __construct(
        #[OA\Property(example: 3)]
        public int $total,
    ) {
    }
}
