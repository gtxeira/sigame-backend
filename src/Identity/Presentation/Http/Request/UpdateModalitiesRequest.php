<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para substituir as modalidades de um usuário que já concluiu o onboarding.
 *
 * Mesmo formato do onboarding, mas sem efeito de progressão: não concede XP.
 */
#[OA\Schema(description: 'Modalidades praticadas e modalidade principal.')]
final readonly class UpdateModalitiesRequest
{
    /**
     * @param list<string> $modalities
     */
    public function __construct(
        #[OA\Property(
            description: 'Modalidades praticadas. Ao menos uma é obrigatória.',
            type: 'array',
            items: new OA\Items(
                type: 'string',
                enum: [Modality::WALKING->value, Modality::RUNNING->value, Modality::CYCLING->value],
            ),
            example: ['corrida', 'caminhada'],
        )]
        #[Assert\All([
            new Assert\Choice(
                callback: [Modality::class, 'values'],
                message: 'A modalidade informada é inválida.',
            ),
        ])]
        public array $modalities = [],

        #[OA\Property(
            description: 'Modalidade principal, quando houver. Deve estar entre as modalidades informadas.',
            example: 'corrida',
            nullable: true,
            enum: [Modality::WALKING->value, Modality::RUNNING->value, Modality::CYCLING->value],
        )]
        #[Assert\Choice(
            callback: [Modality::class, 'values'],
            message: 'A modalidade principal informada é inválida.',
        )]
        public ?string $primaryModality = null,
    ) {
    }
}
