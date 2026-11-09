<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Request;

use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de criação de grupo.
 */
#[OA\Schema(description: 'Dados para criar um grupo esportivo.')]
final readonly class CreateGroupRequest
{
    public function __construct(
        #[OA\Property(description: 'Nome do grupo.', example: 'Corrida no Parque')]
        #[Assert\NotBlank(message: 'O nome do grupo é obrigatório.')]
        #[Assert\Length(
            min: 3,
            max: 80,
            minMessage: 'O nome do grupo deve ter no mínimo {{ limit }} caracteres.',
            maxMessage: 'O nome do grupo deve ter no máximo {{ limit }} caracteres.',
        )]
        public string $name = '',

        #[OA\Property(description: 'Descrição opcional do grupo.', example: 'Encontros aos sábados pela manhã.', nullable: true)]
        #[Assert\Length(max: 500, maxMessage: 'A descrição deve ter no máximo {{ limit }} caracteres.')]
        public ?string $description = null,

        #[OA\Property(
            description: 'Modalidade principal do grupo.',
            example: Modality::RUNNING->value,
            enum: [Modality::WALKING->value, Modality::RUNNING->value, Modality::CYCLING->value],
        )]
        #[Assert\NotBlank(message: 'A modalidade é obrigatória.')]
        #[Assert\Choice(
            callback: [Modality::class, 'values'],
            message: 'A modalidade informada é inválida.',
        )]
        public string $modality = '',

        #[OA\Property(
            description: 'Link externo de comunicação, visível apenas para membros aprovados.',
            example: 'https://chat.example.com/grupo/corrida-no-parque',
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
