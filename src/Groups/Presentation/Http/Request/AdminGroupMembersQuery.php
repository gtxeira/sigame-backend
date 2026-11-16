<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filtro de situação das participações de um grupo na administração.
 */
#[OA\Schema(description: 'Filtro de situação das participações.')]
final readonly class AdminGroupMembersQuery
{
    public function __construct(
        #[OA\Property(
            description: 'Situação da participação; `all` devolve todas.',
            example: 'all',
            enum: ['all', 'pending', 'approved', 'rejected', 'removed'],
        )]
        #[Assert\Choice(
            choices: ['all', 'pending', 'approved', 'rejected', 'removed'],
            message: 'A situação informada é inválida.',
        )]
        public string $status = 'all',
    ) {
    }
}
