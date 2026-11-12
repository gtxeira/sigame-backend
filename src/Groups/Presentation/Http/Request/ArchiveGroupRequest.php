<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO do arquivamento de grupo. O motivo é opcional.
 */
#[OA\Schema(description: 'Dados do arquivamento de grupo.')]
final readonly class ArchiveGroupRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Motivo do arquivamento.',
            example: 'O grupo pausou as atividades.',
            nullable: true,
        )]
        #[Assert\Length(max: 500, maxMessage: 'O motivo deve ter no máximo {{ limit }} caracteres.')]
        public ?string $reason = null,
    ) {
    }
}
