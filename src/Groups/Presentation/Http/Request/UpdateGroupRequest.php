<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO do PATCH parcial de grupo (JSON Merge Patch).
 *
 * A presença de cada campo é conferida no controller. `null` limpa apenas
 * `description` e `communicationLink`; `name` e `modality` não aceitam nulo.
 */
#[OA\Schema(description: 'Campos do grupo a atualizar. Campos ausentes não são alterados.')]
final readonly class UpdateGroupRequest
{
    public function __construct(
        #[OA\Property(description: 'Novo nome do grupo.', example: 'Corrida no Parque', nullable: true)]
        #[Assert\Length(
            min: 3,
            max: 80,
            minMessage: 'O nome do grupo deve ter no mínimo {{ limit }} caracteres.',
            maxMessage: 'O nome do grupo deve ter no máximo {{ limit }} caracteres.',
        )]
        public ?string $name = null,

        #[OA\Property(description: 'Nova descrição. Envie `null` para limpar.', example: 'Novo horário aos domingos.', nullable: true)]
        #[Assert\Length(max: 500, maxMessage: 'A descrição deve ter no máximo {{ limit }} caracteres.')]
        public ?string $description = null,

        #[OA\Property(
            description: 'Nova modalidade. Envie `null` para não alterar.',
            example: Modality::CYCLING->value,
            nullable: true,
            enum: [Modality::WALKING->value, Modality::RUNNING->value, Modality::CYCLING->value],
        )]
        #[Assert\Choice(
            callback: [Modality::class, 'values'],
            message: 'A modalidade informada é inválida.',
        )]
        public ?string $modality = null,

        #[OA\Property(
            description: 'Novo link de comunicação. Envie `null` para limpar.',
            example: 'https://chat.example.com/grupo/novo-link',
            nullable: true,
        )]
        #[Assert\Length(max: 2048, maxMessage: 'O link deve ter no máximo {{ limit }} caracteres.')]
        #[Assert\Regex(
            pattern: '~^https?://~i',
            message: 'O link deve começar com http:// ou https://.',
        )]
        public ?string $communicationLink = null,
    ) {
    }
}
