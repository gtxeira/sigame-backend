<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para confirmar o e-mail com o token recebido por mensagem.
 */
#[OA\Schema(description: 'Token de verificação de e-mail.')]
final readonly class EmailVerifyRequest
{
    public function __construct(
        #[OA\Property(description: 'Token enviado por e-mail.', example: 'a1b2c3...')]
        #[Assert\NotBlank(message: 'O token é obrigatório.')]
        #[Assert\Length(max: 128, maxMessage: 'O token deve ter no máximo 128 caracteres.')]
        public string $token,
    ) {
    }
}
