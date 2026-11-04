<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Security;

use App\Shared\Presentation\Http\Response\ApiErrorCode;
use App\Shared\Presentation\Http\Response\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Resposta JSON 403 para autorização negada em rotas da API.
 */
final class ApiAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return ApiResponse::error([
            [
                'code' => ApiErrorCode::Forbidden->value,
                'message' => ApiErrorCode::Forbidden->message(),
            ],
        ])->toJsonResponse(Response::HTTP_FORBIDDEN);
    }
}
