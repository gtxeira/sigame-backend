<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class DomainErrorResponse extends OA\Response
{
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_CONFLICT,
            description: 'Uma ou mais regras de negócio foram violadas.',
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
                                        new OA\Property(property: 'code', type: 'string', example: 'CPF_ALREADY_EXISTS'),
                                        new OA\Property(property: 'message', type: 'string', example: 'Já existe um usuário com este CPF.'),
                                        new OA\Property(
                                            property: 'details',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'field', type: 'string', example: 'cpf'),
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
