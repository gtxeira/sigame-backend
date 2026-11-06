<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta a resposta 500 padrão, idêntica em todos os endpoints.
 *
 * Nenhum detalhe interno é exposto: o cliente recebe apenas o código
 * INTERNAL_ERROR e uma mensagem genérica.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class InternalErrorResponse extends ApiErrorResponse
{
    public function __construct(?ApiMeta $meta = null)
    {
        parent::__construct(
            response: Response::HTTP_INTERNAL_SERVER_ERROR,
            description: 'Falha inesperada no servidor.',
            examples: [
                'INTERNAL_ERROR' => new ApiErrorExample(
                    message: 'Um erro interno ocorreu. Por favor, aguarde e tente novamente.',
                    summary: 'Erro interno',
                    description: 'Exceção não tratada durante o processamento da requisição.',
                ),
            ],
            meta: $meta,
        );
    }
}
