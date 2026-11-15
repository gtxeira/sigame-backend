<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use OpenApi\Attributes as OA;

/**
 * Resposta genérica do pedido de cadastro.
 *
 * O mesmo corpo é devolvido exista ou não uma conta com o e-mail, para não
 * permitir enumeração. Nenhum dado do usuário é exposto.
 */
#[OA\Schema(description: 'Solicitação de cadastro aceita para processamento.')]
final readonly class RegistrationAcceptedResponse
{
    public function __construct(
        #[OA\Property(description: 'Situação do pedido; o e-mail será enviado se os dados forem válidos.', example: 'pending')]
        public string $status,
    ) {
    }

    public static function pending(): self
    {
        return new self(status: 'pending');
    }
}
