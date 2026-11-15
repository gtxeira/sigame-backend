<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para reenviar o código de verificação de e-mail.
 */
#[OA\Schema(description: 'Reenvio do código de verificação de e-mail.')]
final readonly class EmailResendRequest
{
    public function __construct(
        #[OA\Property(description: 'E-mail da conta.', format: 'email', example: 'user@example.com')]
        #[Assert\NotBlank(message: 'O email é obrigatório.')]
        #[Assert\Email(message: 'O formato do email é inválido.')]
        public string $email = '',
    ) {
    }
}
