<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Motivo da suspensão global.
 */
#[OA\Schema(description: 'Motivo público da suspensão da conta.')]
final readonly class SuspendUserRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Motivo exibido ao titular da conta.',
            example: 'Descumprimento reiterado das regras de convivência.',
            minLength: 3,
            maxLength: 500,
        )]
        #[Assert\NotBlank(message: 'O motivo é obrigatório.')]
        #[Assert\Length(
            min: 3,
            max: 500,
            minMessage: 'O motivo deve ter ao menos {{ limit }} caracteres.',
            maxMessage: 'O motivo deve ter no máximo {{ limit }} caracteres.',
        )]
        public string $reason = '',
    ) {
    }
}
