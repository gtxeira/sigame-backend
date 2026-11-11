<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO do callback OAuth (code + state).
 */
#[OA\Schema(description: 'DTO de callback do provedor social.')]
final readonly class SocialCallbackRequest
{
    public function __construct(
        #[OA\Property(description: 'Authorization code devolvido pelo provedor.', example: '4/0Ab...')]
        #[Assert\NotBlank(message: 'O authorization code é obrigatório.')]
        #[Assert\Length(max: 4096, maxMessage: 'O authorization code deve ter no máximo 4096 caracteres.')]
        public string $code = '',

        #[OA\Property(description: 'Valor de state devolvido pelo provedor.', example: 'a1b2c3')]
        #[Assert\NotBlank(message: 'O state é obrigatório.')]
        #[Assert\Length(max: 128, maxMessage: 'O state deve ter no máximo 128 caracteres.')]
        public string $state = '',
    ) {
    }
}
