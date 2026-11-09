<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;

/**
 * DTO do PATCH parcial de perfil (JSON Merge Patch).
 *
 * A presença de cada campo é conferida no controller, porque `null` tem
 * significado próprio (limpar altura/peso) e a desserialização não distingue
 * campo ausente de campo nulo.
 */
#[OA\Schema(description: 'Campos de perfil a atualizar. Campos ausentes não são alterados.')]
final readonly class UpdateProfileRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Novo nome. Quando enviado, não pode ser vazio.',
            example: 'Maria Silva',
            nullable: true,
        )]
        public ?string $name = null,

        #[OA\Property(
            description: 'Nova altura em centímetros. Envie `null` para limpar.',
            example: 170,
            nullable: true,
        )]
        public ?int $heightCm = null,

        #[OA\Property(
            description: 'Novo peso em quilogramas. Envie `null` para limpar.',
            example: 68.5,
            nullable: true,
        )]
        public ?float $weightKg = null,
    ) {
    }
}
