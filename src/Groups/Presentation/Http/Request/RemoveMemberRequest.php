<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Motivo da remoção de um membro.
 */
#[OA\Schema(description: 'Dados para remover um membro do grupo.')]
final readonly class RemoveMemberRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Motivo da remoção, exibido ao membro removido.',
            example: 'Descumprimento das regras de convivência do grupo.',
        )]
        #[Assert\NotBlank(message: 'O motivo da remoção é obrigatório.')]
        #[Assert\Length(max: 500, maxMessage: 'O motivo deve ter no máximo {{ limit }} caracteres.')]
        public string $reason = '',
    ) {
    }
}
