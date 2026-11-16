<?php

declare(strict_types=1);

namespace App\Administration\Presentation\Http\Request;

use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filtros e paginação da trilha de auditoria.
 */
#[OA\Schema(description: 'Filtros da trilha de auditoria.')]
final readonly class AdminAuditListQuery
{
    public function __construct(
        #[OA\Property(description: 'Página, a partir de 1.', example: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Itens por página, no máximo 30.', example: 20)]
        #[Assert\Range(min: 1, max: 30)]
        public int $perPage = 20,

        #[OA\Property(description: 'Filtra pela ação administrativa.', nullable: true, enum: AdminAuditAction::class)]
        #[Assert\Choice(callback: [AdminAuditAction::class, 'values'], message: 'A ação informada é inválida.')]
        public ?string $action = null,

        #[OA\Property(description: 'Filtra pelo tipo do alvo.', nullable: true, enum: AdminAuditTargetType::class)]
        #[Assert\Choice(callback: [AdminAuditTargetType::class, 'values'], message: 'O tipo de alvo informado é inválido.')]
        public ?string $targetType = null,

        #[OA\Property(description: 'Filtra pelo autor da ação.', format: 'uuid', nullable: true)]
        #[Assert\Uuid(message: 'O autor informado é inválido.')]
        public ?string $actorId = null,

        #[OA\Property(description: 'Data/hora mínima em ISO 8601.', nullable: true)]
        public ?string $from = null,

        #[OA\Property(description: 'Data/hora máxima em ISO 8601.', nullable: true)]
        public ?string $to = null,
    ) {
    }
}
