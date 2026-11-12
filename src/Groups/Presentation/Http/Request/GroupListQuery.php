<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filtros e paginação da listagem de grupos.
 */
#[OA\Schema(description: 'Filtros da listagem de grupos.')]
final readonly class GroupListQuery
{
    public function __construct(
        #[OA\Property(description: 'Página, a partir de 1.', example: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Itens por página, no máximo 30.', example: 20)]
        #[Assert\Range(min: 1, max: 30)]
        public int $perPage = 20,

        #[OA\Property(
            description: 'Filtra pela modalidade do grupo.',
            example: Modality::RUNNING->value,
            nullable: true,
            enum: [Modality::WALKING->value, Modality::RUNNING->value, Modality::CYCLING->value],
        )]
        #[Assert\Choice(
            callback: [Modality::class, 'values'],
            message: 'A modalidade informada é inválida.',
        )]
        public ?string $modality = null,

        #[OA\Property(
            description: 'Filtra pelo estado do grupo.',
            example: GroupStatus::FORMING->value,
            nullable: true,
            enum: [GroupStatus::FORMING->value, GroupStatus::ACTIVE->value, GroupStatus::FULL->value],
        )]
        #[Assert\Choice(
            choices: [GroupStatus::FORMING->value, GroupStatus::ACTIVE->value, GroupStatus::FULL->value],
            message: 'O estado informado é inválido.',
        )]
        public ?string $status = null,

        #[OA\Property(description: 'Busca parcial pelo nome.', example: 'parque', nullable: true)]
        #[Assert\Length(max: 80)]
        public ?string $name = null,

        #[OA\Property(description: 'Quando verdadeiro, retorna apenas grupos que ainda aceitam membros.', example: true)]
        public bool $availableOnly = false,

        #[OA\Property(
            description: 'Início do intervalo (ISO 8601) para filtrar grupos com atividade prevista ou em andamento. '
                .'Deve ser enviado junto com `activityTo`.',
            format: 'date-time',
            example: '2026-11-12T03:00:00.000Z',
            nullable: true,
        )]
        public ?string $activityFrom = null,

        #[OA\Property(
            description: 'Fim (exclusivo) do intervalo de atividades. Deve ser enviado junto com `activityFrom`.',
            format: 'date-time',
            example: '2026-11-13T03:00:00.000Z',
            nullable: true,
        )]
        public ?string $activityTo = null,
    ) {
    }
}
