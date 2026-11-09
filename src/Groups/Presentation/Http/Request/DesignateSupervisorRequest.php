<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Membro aprovado que será designado supervisor.
 */
#[OA\Schema(description: 'Dados para designar o supervisor do grupo.')]
final readonly class DesignateSupervisorRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Identificador da participação aprovada.',
            format: 'uuid',
            example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f',
        )]
        #[Assert\NotBlank(message: 'A participação é obrigatória.')]
        #[Assert\Uuid(message: 'A participação informada é inválida.')]
        public string $membershipId = '',
    ) {
    }
}
