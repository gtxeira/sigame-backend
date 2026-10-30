<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidationErrorResponse extends ApiErrorResponse
{
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_UNPROCESSABLE_ENTITY,
            description: 'Erros de validação de entrada.',
            examples: [
                'VALIDATION_ERROR' => new ApiErrorExample(
                    summary: 'Erros de Validação',
                    when: 'Um ou mais campos do payload contêm dados inválidos.',
                    value: [
                        'errors' => [
                            [
                                'code' => 'VALIDATION_ERROR',
                                'message' => 'Um ou mais erros de validação foram encontrados.',
                                'details' => [
                                    [
                                        'field' => 'email',
                                        'message' => 'O email é obrigatório.',
                                    ],
                                ],
                            ],
                        ],
                        'meta' => ['timestamp' => '2026-09-21T10:00:00.000+00:00'],
                    ],
                ),
            ],
        );
    }
}
