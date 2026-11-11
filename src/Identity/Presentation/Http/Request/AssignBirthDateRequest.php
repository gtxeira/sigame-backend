<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para definir a data de nascimento do usuário autenticado.
 */
#[OA\Schema(description: 'DTO para definir a data de nascimento do usuário autenticado.')]
final readonly class AssignBirthDateRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Data de nascimento no formato AAAA-MM-DD.',
            example: '1990-05-12',
            format: 'date',
        )]
        #[Assert\NotBlank(message: 'A data de nascimento é obrigatória.')]
        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}$/',
            message: 'A data de nascimento deve estar no formato AAAA-MM-DD.',
        )]
        public string $birthDate = '',
    ) {
    }
}
