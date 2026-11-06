<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de início do fluxo OAuth.
 */
#[OA\Schema(description: 'DTO para iniciar o fluxo de login social.')]
final readonly class SocialStartRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Tipo de cliente que iniciará a autorização. `dev` só existe fora de produção.',
            example: 'web',
            enum: ['web', 'dev'],
        )]
        #[Assert\NotBlank(message: 'O cliente é obrigatório.')]
        #[Assert\Choice(
            choices: ['web', 'dev'],
            message: 'O cliente deve ser "web" ou "dev".',
        )]
        public string $client,
    ) {
    }
}
