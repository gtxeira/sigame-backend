<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para redefinir a senha com o token recebido por e-mail.
 */
#[OA\Schema(description: 'Redefinição de senha por token.')]
final readonly class PasswordResetRequest
{
    public function __construct(
        #[OA\Property(description: 'Token enviado por e-mail.', example: 'a1b2c3...')]
        #[Assert\NotBlank(message: 'O token é obrigatório.')]
        #[Assert\Length(max: 128, maxMessage: 'O token deve ter no máximo 128 caracteres.')]
        public string $token,

        #[OA\Property(description: 'Nova senha.', example: 'secret123')]
        #[Assert\NotBlank(message: 'A senha é obrigatória.')]
        #[Assert\Length(
            min: 8,
            max: 255,
            minMessage: 'A senha deve ter no mínimo 8 caracteres.',
            maxMessage: 'A senha deve ter no máximo 255 caracteres.',
        )]
        public string $password,
    ) {
    }
}
