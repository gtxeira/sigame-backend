<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Identity\Domain\User\Entity\UserSuspension;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Registro do histórico de suspensão/reativação de uma conta.')]
final readonly class UserSuspensionResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(example: 'SUSPENDED', enum: ['SUSPENDED', 'REACTIVATED'])]
        public string $action,

        #[OA\Property(description: 'Motivo público informado pela administração.', example: 'Descumprimento das regras.', nullable: true)]
        public ?string $reason,

        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $actorId,

        #[OA\Property(example: 'Administrador SIGAME')]
        public string $actorName,

        #[OA\Property(format: 'date-time', example: '2026-11-10T10:00:00.000+00:00')]
        public string $occurredAt,
    ) {
    }

    public static function fromEntity(UserSuspension $suspension): self
    {
        return new self(
            id: $suspension->id()->value(),
            action: $suspension->action()->value,
            reason: $suspension->reason(),
            actorId: $suspension->actor()->id()->value(),
            actorName: $suspension->actor()->name(),
            occurredAt: $suspension->occurredAt()->toIsoStringWithMilliseconds(),
        );
    }
}
