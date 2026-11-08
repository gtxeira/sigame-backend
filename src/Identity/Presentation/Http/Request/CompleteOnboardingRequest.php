<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use App\Shared\Domain\ValueObject\Modality;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de request para conclusão do onboarding.
 *
 * O request valida apenas tipo e formato. As faixas de altura e peso, a
 * exigência de ao menos uma modalidade e o vínculo da modalidade principal
 * pertencem ao agregado, que devolve códigos de erro de negócio específicos.
 */
#[OA\Schema(description: 'Dados para concluir o onboarding.')]
final readonly class CompleteOnboardingRequest
{
    /**
     * @param list<string> $modalities
     */
    public function __construct(
        #[OA\Property(description: 'Altura em centímetros.', example: 178, nullable: true)]
        public ?int $heightCm = null,

        #[OA\Property(description: 'Peso em quilogramas.', example: 75.5, nullable: true)]
        public ?float $weightKg = null,

        #[OA\Property(description: 'URL da foto de perfil.', example: 'https://example.com/photo.jpg', nullable: true, maxLength: 2048)]
        #[Assert\Url(message: 'A URL da foto de perfil é inválida.')]
        #[Assert\Length(max: 2048, maxMessage: 'A URL da foto de perfil deve ter no máximo 2048 caracteres.')]
        public ?string $photoUrl = null,

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
