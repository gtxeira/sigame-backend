<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO do vínculo por ticket entregue no 409.
 */
#[OA\Schema(description: 'DTO para vincular uma identidade social após o login local.')]
final readonly class SocialLinkRequest
{
    public function __construct(
        #[OA\Property(description: 'Ticket de vínculo recebido na resposta 409.', example: 'a1b2c3...')]
        #[Assert\NotBlank(message: 'O linkToken é obrigatório.')]
        #[Assert\Length(max: 128, maxMessage: 'O linkToken deve ter no máximo 128 caracteres.')]
        public string $linkToken = '',
    ) {
    }
}
