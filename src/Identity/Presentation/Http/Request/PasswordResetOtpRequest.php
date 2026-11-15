<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para validar o código de redefinição de senha.
 */
#[OA\Schema(description: 'Validação do código de redefinição de senha.')]
final readonly class PasswordResetOtpRequest
{
    public function __construct(
        #[OA\Property(description: 'E-mail da conta.', format: 'email', example: 'user@example.com')]
        #[Assert\NotBlank(message: 'O email é obrigatório.')]
        #[Assert\Email(message: 'O formato do email é inválido.')]
        public string $email = '',

        #[OA\Property(description: 'Código de 6 dígitos enviado por e-mail.', example: '123456')]
        #[Assert\NotBlank(message: 'O código é obrigatório.')]
        #[Assert\Regex(pattern: '/^\d{6}$/', message: 'O código deve ter 6 dígitos.')]
        public string $code = '',
    ) {
    }
}
