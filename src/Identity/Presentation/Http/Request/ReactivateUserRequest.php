<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Motivo opcional da reativação global.
 */
#[OA\Schema(description: 'Motivo opcional da reativação da conta.')]
final readonly class ReactivateUserRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Motivo exibido ao titular da conta, quando informado.',
            example: 'Revisão concluída.',
            nullable: true,
            minLength: 3,
            maxLength: 500,
        )]
        #[Assert\Length(
            min: 3,
            max: 500,
            minMessage: 'O motivo deve ter ao menos {{ limit }} caracteres.',
            maxMessage: 'O motivo deve ter no máximo {{ limit }} caracteres.',
        )]
        public ?string $reason = null,
    ) {
    }
}
