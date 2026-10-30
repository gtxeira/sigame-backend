<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ResourceConflictResponse extends ApiErrorResponse
{
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_CONFLICT,
            description: 'A operação conflita com o estado atual do recurso.',
            examples: [
                'RESOURCE_CONFLICT' => new ApiErrorExample(
                    summary: 'Conflito de regra de negócio ou integridade',
                    when: 'A requisição entra em conflito com o estado do recurso.',
                    value: [
                        'errors' => [
                            [
                                'code' => 'RESOURCE_CONFLICT',
                                'message' => 'A operação conflita com o estado atual do recurso.',
                                'details' => [
                                    ['field' => 'field'],
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
