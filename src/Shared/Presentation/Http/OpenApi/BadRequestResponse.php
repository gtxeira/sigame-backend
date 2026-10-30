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
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_BAD_REQUEST,
            description: 'Corpo ilegível: JSON malformado.',
            examples: [
                'BAD_REQUEST' => new ApiExample(
                    summary: 'Payload ilegível',
                    description: 'Corpo que não é um JSON válido.',
                    value: [
                        'errors' => [
                            [
                                'code' => 'BAD_REQUEST',
                                'message' => 'A requisição contém dados inválidos.',
                            ],
                        ],
                        'meta' => ['timestamp' => '2026-09-21T10:00:00.000+00:00'],
                    ],
                ),
            ],
        );
    }
}
