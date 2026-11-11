<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para trocar a senha de uma conta autenticada.
 */
#[OA\Schema(description: 'Troca de senha autenticada.')]
final readonly class PasswordChangeRequest
{
    public function __construct(
        #[OA\Property(description: 'Senha atual.', example: 'secret123')]
        #[Assert\NotBlank(message: 'A senha atual é obrigatória.')]
        #[Assert\Length(max: 255, maxMessage: 'A senha atual deve ter no máximo 255 caracteres.')]
        public string $currentPassword = '',

        #[OA\Property(description: 'Nova senha.', example: 'new-secret-123')]
        #[Assert\NotBlank(message: 'A nova senha é obrigatória.')]
        #[Assert\Length(
            min: 8,
            max: 255,
            minMessage: 'A nova senha deve ter no mínimo 8 caracteres.',
            maxMessage: 'A nova senha deve ter no máximo 255 caracteres.',
        )]
        public string $newPassword = '',
    ) {
    }
}
