<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;

/**
 * DTO de request para solicitação de cadastro.
 *
 * A validação semântica fica nos Value Objects do handler, para acumular
 * todos os erros de campo em uma única resposta.
 */
#[OA\Schema(description: 'DTO de request para solicitação de cadastro.')]
final readonly class RegisterUserRequest
{
    public function __construct(
        #[OA\Property(description: 'Endereço de e-mail para ser usado como login na plataforma.', example: 'user@example.com')]
        public string $email = '',

        #[OA\Property(description: 'Nome completo do usuário.', example: 'John Doe')]
        public string $name = '',

        #[OA\Property(description: 'Senha para login na plataforma.', example: 'secret123')]
        public string $password = '',
    ) {
    }
}
