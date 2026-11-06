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
    public function __construct(
        private readonly ErrorStatusResolver $errorStatusResolver,
    ) {
    }

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
    public function failure(Result $result): JsonResponse
    {
        return $this->failureWithMeta($result, []);
    }

    /**
     * @param Result<mixed>        $result
     * @param array<string, mixed> $meta
     */
    public function failureWithMeta(Result $result, array $meta): JsonResponse
    {
        $errors = ApiResponseErrorsMapper::fromDomainErrors($result->errors());

        return ApiResponse::error($errors, $meta)
            ->toJsonResponse($this->errorStatusResolver->fromErrors($result->errors()));
    }
}
