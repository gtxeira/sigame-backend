<?php

declare(strict_types=1);

namespace App\Notifications\Presentation\Http\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Quantidade de notificações marcadas como lidas.')]
final readonly class MarkAllReadResponse
{
    public function __construct(
        #[OA\Property(example: 5)]
        public int $marked,
    ) {
    }
}
