<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\GroupVisibility;
use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filtros e paginação da listagem administrativa de grupos.
 */
#[OA\Schema(description: 'Filtros da listagem administrativa de grupos.')]
final readonly class AdminGroupListQuery
{
    public function __construct(
        #[OA\Property(description: 'Página, a partir de 1.', example: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Itens por página, no máximo 30.', example: 20)]
        #[Assert\Range(min: 1, max: 30)]
        public int $perPage = 20,

        #[OA\Property(description: 'Busca parcial pelo nome.', example: 'parque', nullable: true)]
        #[Assert\Length(max: 80)]
        public ?string $query = null,

        #[OA\Property(
            description: 'Filtra pela modalidade do grupo.',
            example: Modality::RUNNING->value,
            nullable: true,
            enum: [Modality::WALKING->value, Modality::RUNNING->value, Modality::CYCLING->value],
        )]
        #[Assert\Choice(callback: [Modality::class, 'values'], message: 'A modalidade informada é inválida.')]
        public ?string $modality = null,

        #[OA\Property(
            description: 'Filtra pelo estado derivado do grupo.',
            example: GroupStatus::ACTIVE->value,
            nullable: true,
            enum: [GroupStatus::FORMING->value, GroupStatus::ACTIVE->value, GroupStatus::FULL->value],
        )]
        #[Assert\Choice(
            choices: [GroupStatus::FORMING->value, GroupStatus::ACTIVE->value, GroupStatus::FULL->value],
            message: 'O estado informado é inválido.',
        )]
        public ?string $status = null,

        #[OA\Property(
            description: 'Recorte de visibilidade: ativos, arquivados, excluídos ou todos.',
            example: GroupVisibility::Visible->value,
            enum: GroupVisibility::class,
        )]
        #[Assert\Choice(callback: [GroupVisibility::class, 'values'], message: 'A visibilidade informada é inválida.')]
        public string $visibility = GroupVisibility::Visible->value,
    ) {
    }
}
