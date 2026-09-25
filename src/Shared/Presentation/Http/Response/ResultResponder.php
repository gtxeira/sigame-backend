<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Response;

use App\Shared\Domain\Functional\Result;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Converte Result em respostas HTTP.
 *
 * Centraliza a lógica de mapeamento de erros de domínio para status HTTP.
 */
final class ResultResponder
{
    /**
     * @param Result<mixed> $result
     */
    public function success(Result $result, int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return ApiResponse::ok($result->value())
            ->toJsonResponse($statusCode);
    }

    /**
     * @param Result<list<mixed>> $result
     */
    public function paginated(
        Result $result,
        int $page,
        int $perPage,
        int $total,
        int $statusCode = Response::HTTP_OK,
    ): JsonResponse {
        return ApiResponse::ok($result->value())
            ->withPagination($page, $perPage, $total)
            ->toJsonResponse($statusCode);
    }

    /**
     * @param Result<mixed> $result
     */
    public function failure(Result $result, int $statusCode = Response::HTTP_CONFLICT): JsonResponse
    {
        $errors = ApiResponseErrorsMapper::fromDomainErrors($result->errors());

        return ApiResponse::error($errors)
            ->toJsonResponse($statusCode);
    }
}
