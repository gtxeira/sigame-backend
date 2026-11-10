<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Domain\User\ValueObject\UserStatus;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filtros e paginação da listagem administrativa de usuários.
 */
#[OA\Schema(description: 'Filtros da listagem administrativa de usuários.')]
final readonly class AdminUserListQuery
{
    public function __construct(
        #[OA\Property(description: 'Página, a partir de 1.', example: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Itens por página, no máximo 30.', example: 20)]
        #[Assert\Range(min: 1, max: 30)]
        public int $perPage = 20,

        #[OA\Property(description: 'Busca parcial por nome ou e-mail.', example: 'ana', nullable: true)]
        #[Assert\Length(max: 100)]
        public ?string $query = null,

        #[OA\Property(
            description: 'Filtra pelo estado da conta.',
            example: UserStatus::ACTIVE->value,
            nullable: true,
            enum: [UserStatus::ACTIVE->value, UserStatus::SUSPENDED->value],
        )]
        #[Assert\Choice(
            choices: [UserStatus::ACTIVE->value, UserStatus::SUSPENDED->value],
            message: 'O estado informado é inválido.',
        )]
        public ?string $status = null,

        #[OA\Property(
            description: 'Filtra pelo papel do usuário.',
            example: UserRole::USER->value,
            nullable: true,
            enum: [UserRole::USER->value, UserRole::ADMIN_SIGAME->value],
        )]
        #[Assert\Choice(
            choices: [UserRole::USER->value, UserRole::ADMIN_SIGAME->value],
            message: 'O papel informado é inválido.',
        )]
        public ?string $role = null,
    ) {
    }
}
