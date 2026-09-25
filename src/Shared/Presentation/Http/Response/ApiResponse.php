<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Response;

use App\Shared\Domain\ValueObject\Instant;
use JsonSerializable;
use Symfony\Component\HttpFoundation\JsonResponse;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * @phpstan-type PaginationMeta array{
 *     page: int,
 *     perPage: int,
 *     total: int,
 *     totalPages: int
 * }
 * @phpstan-type ApiResponseMetaInput array{
 *     timestamp?: string,
 *     pagination?: PaginationMeta
 * } & array<string, mixed>
 * @phpstan-type ApiResponseMeta array{
 *     timestamp: string,
 *     pagination?: PaginationMeta
 * } & array<string, mixed>
 * @phpstan-type ApiErrorDetail array{
 *     field?: string,
 *     message?: string
 * }
 * @phpstan-type ApiErrorItem array{
 *     code: string,
 *     message: string,
 *     details?: list<ApiErrorDetail>
 * }
 * @phpstan-type ApiErrors list<ApiErrorItem>
 */
readonly class ApiResponse implements JsonSerializable
{
    /** @var ApiResponseMeta */
    public array $meta;

    /**
     * @param ApiResponseMetaInput $meta
     * @param ApiErrors            $errors
     */
    public function __construct(
        public bool $isSuccess,
        public mixed $data = null,
        array $meta = [],
        public array $errors = [],
    ) {
        $meta['timestamp'] ??= self::getTimestamp();
        $this->meta = $meta;
    }

    /**
     * @param array<string, mixed> $extraMeta chaves customizadas `request_id`
     */
    public static function ok(mixed $data = null, array $extraMeta = []): self
    {
        return new self(
            isSuccess: true,
            data: $data,
            meta: $extraMeta,
            errors: [],
        );
    }

    /**
     * @param ApiErrors            $errors
     * @param array<string, mixed> $extraMeta
     */
    public static function error(array $errors, array $extraMeta = []): self
    {
        return new self(
            isSuccess: false,
            data: null,
            meta: $extraMeta,
            errors: $errors,
        );
    }

    public function withPagination(int $page, int $perPage, int $total): self
    {
        $page = max(1, $page);
        $perPage = min(30, max(1, $perPage));
        $total = max(0, $total);
        $totalPages = (int) max(0, ceil($total / $perPage));

        return new self(
            isSuccess: true,
            data: $this->data,
            meta: array_merge($this->meta, [
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                ],
            ]),
            errors: $this->errors,
        );
    }

    public function toJsonResponse(int $statusCode = 200): JsonResponse
    {
        $response = new JsonResponse($this, $statusCode);

        $response->setEncodingOptions(
            $response->getEncodingOptions()
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES,
        );

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        if ($this->isSuccess) {
            return array_filter([
                'data' => $this->data,
                'meta' => $this->meta,
            ]);
        }

        return array_filter([
            'errors' => $this->errors,
            'meta' => $this->meta,
        ]);
    }

    private static function getTimestamp(): string
    {
        return Instant::now()->toIsoStringWithMilliseconds();
    }
}
