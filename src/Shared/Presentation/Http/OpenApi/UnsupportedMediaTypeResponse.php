<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta a resposta `415 Unsupported Media Type`.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class UnsupportedMediaTypeResponse extends ApiErrorResponse
{
    public function __construct(?ApiMeta $meta = null)
    {
        parent::__construct(
            response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            description: '`Content-Type` ausente ou diferente de `application/json`.',
            examples: [
                'UNSUPPORTED_MEDIA_TYPE' => new ApiErrorExample(
                    message: 'O formato do conteúdo enviado não é suportado. Utilize application/json.',
                    summary: 'Formato não suportado',
                    description: 'Requisição sem `Content-Type` ou com formato diferente de `application/json`.',
                ),
            ],
            meta: $meta,
        );
    }
}
