<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para definir o CPF do usuário autenticado.
 */
#[OA\Schema(description: 'DTO para definir o CPF do usuário autenticado.')]
final readonly class AssignCpfRequest
{
    public function __construct(
        #[OA\Property(description: 'CPF do usuário, podendo vir formatado.', example: '477.051.220-14')]
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
    ) {
    }
}
