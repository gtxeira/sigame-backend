<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidationErrorResponse extends OA\Response
{
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_UNPROCESSABLE_ENTITY,
            description: 'Um ou mais erros de validação foram encontrados.',
            content: new OA\JsonContent(
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/ApiResponseError'),
                    new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: 'errors',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_ERROR'),
                                        new OA\Property(property: 'message', type: 'string', example: 'Um ou mais erros de validação foram encontrados.'),
                                        new OA\Property(
                                            property: 'details',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'field', type: 'string', example: 'email'),
                                                    new OA\Property(property: 'message', type: 'string', example: 'O email é obrigatório.'),
                                                ],
                                                type: 'object',
                                            ),
                                        ),
                                    ],
                                    type: 'object',
                                ),
                            ),
                        ],
                    ),
                ],
            ),
        );
    }
}
