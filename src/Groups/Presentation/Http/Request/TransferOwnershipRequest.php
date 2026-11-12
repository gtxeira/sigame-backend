<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Membro aprovado que assumirá a responsabilidade pelo grupo.
 */
#[OA\Schema(description: 'Dados para transferir a responsabilidade pelo grupo.')]
final readonly class TransferOwnershipRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Identificador da participação aprovada que assumirá o grupo.',
            format: 'uuid',
            example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f',
        )]
        #[Assert\NotBlank(message: 'A participação é obrigatória.')]
        #[Assert\Uuid(message: 'A participação informada é inválida.')]
        public string $membershipId = '',
    ) {
    }
}
