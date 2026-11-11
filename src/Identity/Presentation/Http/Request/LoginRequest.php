<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de login local.
 */
#[OA\Schema(description: 'DTO de login com e-mail e senha.')]
final readonly class LoginRequest
{
    public function __construct(
        #[OA\Property(description: 'E-mail cadastrado.', format: 'email', example: 'user@example.com')]
        #[Assert\NotBlank(message: 'O email é obrigatório.')]
        #[Assert\Email(message: 'O formato do email é inválido.')]
        public string $email = '',

        #[OA\Property(description: 'Senha do usuário.', example: 'secret123')]
        #[Assert\NotBlank(message: 'A senha é obrigatória.')]
        #[Assert\Length(max: 255, maxMessage: 'A senha deve ter no máximo 255 caracteres.')]
        public string $password = '',
    ) {
    }
}
