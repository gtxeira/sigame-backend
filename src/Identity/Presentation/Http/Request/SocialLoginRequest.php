<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de login social por authorization code.
 */
#[OA\Schema(description: 'DTO de login social por authorization code.')]
final readonly class SocialLoginRequest
{
    public function __construct(
        #[OA\Property(description: 'Authorization code recebido do provedor.', example: '4/0Ab...')]
        #[Assert\NotBlank(message: 'O authorization code é obrigatório.')]
        public string $code,

        #[OA\Property(description: 'Redirect URI usada na autorização; precisa estar na allowlist do provedor.')]
        #[Assert\NotBlank(message: 'O redirect_uri é obrigatório.')]
        #[Assert\Length(max: 2048, maxMessage: 'O redirect_uri deve ter no máximo 2048 caracteres.')]
        public string $redirectUri,

        #[OA\Property(description: 'Code verifier do PKCE, quando usado pelo cliente.', nullable: true)]
        public ?string $codeVerifier = null,

        #[OA\Property(description: 'Nome do usuário, usado quando o provedor não devolve o nome (Apple).', nullable: true)]
        public ?string $name = null,
    ) {
    }
}
