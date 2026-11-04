<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta a resposta `429 Too Many Requests`.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class TooManyRequestsResponse extends ApiErrorResponse
{
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_TOO_MANY_REQUESTS,
            description: 'Limite de tentativas excedido.',
            examples: [
                'TOO_MANY_REQUESTS' => new ApiErrorExample(
                    message: 'Muitas tentativas. Tente novamente em instantes.',
                    summary: 'Limite excedido',
                    description: 'O cliente excedeu o limite de requisições para este endpoint.',
                ),
            ],
        );
    }
}
