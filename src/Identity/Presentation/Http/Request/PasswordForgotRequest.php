<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para solicitar o reset de senha.
 */
#[OA\Schema(description: 'Solicitação de redefinição de senha.')]
final readonly class PasswordForgotRequest
{
    public function __construct(
        #[OA\Property(description: 'E-mail da conta.', format: 'email', example: 'user@example.com')]
        #[Assert\NotBlank(message: 'O email é obrigatório.')]
        #[Assert\Email(message: 'O formato do email é inválido.')]
        public string $email = '',
    ) {
    }
}
