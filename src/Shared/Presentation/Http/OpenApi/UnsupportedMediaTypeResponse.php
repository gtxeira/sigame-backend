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
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            description: '`Content-Type` ausente ou diferente de `application/json`.',
            examples: [
                'UNSUPPORTED_MEDIA_TYPE' => new ApiExample(
                    summary: 'Formato não suportado',
                    description: 'Requisição sem `Content-Type` ou com formato diferente de `application/json`.',
                    value: [
                        'errors' => [
                            [
                                'code' => 'UNSUPPORTED_MEDIA_TYPE',
                                'message' => 'O formato do conteúdo enviado não é suportado. Utilize application/json.',
                            ],
                        ],
                        'meta' => ['timestamp' => '2026-09-21T10:00:00.000+00:00'],
                    ],
                ),
            ],
        );
    }
}
