<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use App\Groups\Domain\Group\ValueObject\MembershipRole;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Novo papel de um membro aprovado.
 */
#[OA\Schema(description: 'Novo papel do membro no grupo.')]
final readonly class ChangeMembershipRoleRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Papel do membro. `admin` pode executar todas as operações de gestão.',
            example: MembershipRole::ADMIN->value,
            enum: [MembershipRole::ADMIN->value, MembershipRole::MEMBER->value],
        )]
        #[Assert\NotBlank(message: 'O papel é obrigatório.')]
        #[Assert\Choice(
            choices: [MembershipRole::ADMIN->value, MembershipRole::MEMBER->value],
            message: 'O papel informado é inválido.',
        )]
        public string $role = '',
    ) {
    }
}
