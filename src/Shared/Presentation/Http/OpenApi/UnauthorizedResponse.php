<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta a resposta `401 Unauthorized`.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class UnauthorizedResponse extends ApiErrorResponse
{
    /**
     * @param array<string, ApiErrorExample> $examples
     * @param array<string, string>          $fields
     */
    public function __construct(array $examples = [], array $fields = [])
    {
        parent::__construct(
            response: Response::HTTP_UNAUTHORIZED,
            description: 'Autenticação necessária ou credenciais inválidas.',
            examples: [] === $examples ? [
                'UNAUTHORIZED' => new ApiErrorExample(
                    message: 'Autenticação necessária.',
                    summary: 'Não autenticado',
                    description: 'A requisição não apresentou um access token válido.',
                ),
            ] : $examples,
            fields: $fields,
        );
    }
}
