<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;

/**
 * DTO de request para registro de usuário.
 *
 * A validação semântica fica nos Value Objects do handler, para acumular
 * todos os erros de campo em uma única resposta.
 */
#[OA\Schema(description: 'DTO de request para registro de usuário.')]
final readonly class RegisterUserRequest
{
    public function __construct(
        #[OA\Property(
            description: 'CPF do usuário. Opcional no cadastro; quando informado, pode vir formatado.',
            example: '477.051.220-14',
            nullable: true,
        )]
        public ?string $cpf = null,

        #[OA\Property(description: 'Endereço de e-mail para ser usado como login na plataforma.', example: 'user@example.com')]
        public string $email = '',

        #[OA\Property(description: 'Nome completo do usuário.', example: 'John Doe')]
        public string $name = '',

        #[OA\Property(description: 'Senha para login na plataforma.', example: 'secret123')]
        public string $password = '',
    ) {
    }
}
