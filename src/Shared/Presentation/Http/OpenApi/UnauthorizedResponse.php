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
            description: 'Autenticação necessária, token inválido ou expirado.',
            examples: [] === $examples ? [
                'ACCESS_TOKEN_MISSING' => new ApiErrorExample(
                    message: 'Token de acesso ausente.',
                    summary: 'Token ausente',
                    description: 'A requisição não apresentou um access token.',
                ),
                'ACCESS_TOKEN_INVALID' => new ApiErrorExample(
                    message: 'Token de acesso inválido.',
                    summary: 'Token inválido',
                    description: 'O access token é malformado, tem assinatura/claims inválidos ou pertence a um usuário inexistente.',
                ),
                'ACCESS_TOKEN_EXPIRED' => new ApiErrorExample(
                    message: 'Token de acesso expirado. Renove a sessão.',
                    summary: 'Token expirado',
                    description: 'O access token passou do prazo de validade e deve ser renovado pelo refresh token.',
                ),
            ] : $examples,
            fields: $fields,
        );
    }
}
