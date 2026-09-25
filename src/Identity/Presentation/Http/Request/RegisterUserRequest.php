<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de request para registro de usuário.
 */
#[OA\Schema(description: 'DTO de request para registro de usuário.')]
final readonly class RegisterUserRequest
{
    public function __construct(
        #[OA\Property(description: 'CPF do usuário, podendo ou não, ser formatado com pontuação.', example: '477.051.220-14')]
        #[Assert\NotBlank(message: 'O CPF é obrigatório.')]
        #[Assert\Length(
            min: 11,
            max: 14,
            minMessage: 'O CPF deve ter no mínimo 11 dígitos.',
            maxMessage: 'O CPF deve ter no máximo 14 dígitos.',
        )]
        #[Assert\Regex(
            pattern: '/^(\d{11}|\d{3}\.\d{3}\.\d{3}-\d{2})$/',
            message: 'O CPF deve conter apenas números ou estar no formato 000.000.000-00.',
        )]
        public string $cpf,

        #[OA\Property(description: 'Endereço de e-mail para ser usado como login na plataforma.', example: 'user@example.com')]
        #[Assert\NotBlank(message: 'O email é obrigatório.')]
        #[Assert\Email(message: 'O formato do email é inválido.')]
        public string $email,

        #[OA\Property(description: 'Nome completo do usuário.', example: 'John Doe')]
        #[Assert\NotBlank(message: 'O nome é obrigatório.')]
        #[Assert\Length(
            min: 2,
            max: 255,
            minMessage: 'O nome deve ter no mínimo 2 caracteres.',
            maxMessage: 'O nome deve ter no máximo 255 caracteres.',
        )]
        public string $name,

        #[OA\Property(description: 'Senha para login na plataforma.', example: 'secret123')]
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
