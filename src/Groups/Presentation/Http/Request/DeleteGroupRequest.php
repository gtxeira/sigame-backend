<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO da exclusão lógica de grupo. O motivo é opcional.
 */
#[OA\Schema(description: 'Dados da exclusão de grupo.')]
final readonly class DeleteGroupRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Motivo da exclusão.',
            example: 'O grupo foi encerrado.',
            nullable: true,
        )]
        #[Assert\Length(max: 500, maxMessage: 'O motivo deve ter no máximo {{ limit }} caracteres.')]
        public ?string $reason = null,
    ) {
    }
}
