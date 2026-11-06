<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta a resposta `400 Bad Request`.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class BadRequestResponse extends ApiErrorResponse
{
    public function __construct(?ApiMeta $meta = null)
    {
        parent::__construct(
            response: Response::HTTP_BAD_REQUEST,
            description: 'Corpo ilegível: JSON malformado.',
            examples: [
                'BAD_REQUEST' => new ApiErrorExample(
                    message: 'A requisição contém dados inválidos.',
                    summary: 'Payload ilegível',
                    description: 'Corpo que não é um JSON válido.',
                ),
            ],
            meta: $meta,
        );
    }
}
