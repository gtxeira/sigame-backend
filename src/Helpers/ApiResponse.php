<?php

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class ApiResponse implements JsonSerializable
{
    public function __construct(
        public bool $isSuccess,
        public mixed $data = null,
        public array $meta = [],
        public array $errors = [],
    ) { }

    public static function ok(mixed $data = null, array $extraMeta = []): self
    {
        return new self(
            isSuccess: true,
            data: $data,
            meta: array_merge([
                'timestamp' => self::getTimestamp(),
            ], $extraMeta),
            errors: [],
        );
    }

    public static function error(array $errors, int $statusCode, array $extraMeta = []): self
    {
        return new self(
            isSuccess: false,
            data: null,
            meta: array_merge([
                'timestamp' => self::getTimestamp(),
            ], $extraMeta),
            errors: $errors,
        );
    }

    public function withPagination(int $page, int $perPage, int $total): self
    {
        $totalPages = (int) ceil($total / $perPage);

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
        return new JsonResponse($this, $statusCode);
    }

    public function jsonSerialize(): array
    {
        if ($this->isSuccess) {
            return array_filter([
                'data' => $this->data,
                'meta' => $this->meta,
            ], static fn($value) => $value !== null);
        }

        return array_filter([
            'meta' => $this->meta,
            'errors' => $this->errors,
        ], static fn($value) => $value !== null);
    }

    private static function getTimestamp(): string
    {
        return new DateTimeImmutable()->format(DateTimeInterface::RFC3339_EXTENDED);
    }
}
