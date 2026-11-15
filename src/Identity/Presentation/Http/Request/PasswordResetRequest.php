<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para redefinir a senha com o ticket temporário.
 */
#[OA\Schema(description: 'Redefinição de senha por ticket temporário.')]
final readonly class PasswordResetRequest
{
    public function __construct(
        #[OA\Property(description: 'Ticket temporário devolvido pela validação do código.', example: 'a1b2c3...')]
        #[Assert\NotBlank(message: 'A autorização de redefinição é obrigatória.')]
        #[Assert\Length(max: 128, maxMessage: 'A autorização deve ter no máximo 128 caracteres.')]
        public string $resetToken = '',

        #[OA\Property(description: 'Nova senha.', example: 'secret123')]
        #[Assert\NotBlank(message: 'A senha é obrigatória.')]
        #[Assert\Length(
            min: 8,
            max: 255,
            minMessage: 'A senha deve ter no mínimo 8 caracteres.',
            maxMessage: 'A senha deve ter no máximo 255 caracteres.',
        )]
        public string $password = '',
    ) {
    }
}
