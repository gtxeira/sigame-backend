<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Response;

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\ValidationErrorCode;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Presentation\Http\Validation\ConstraintViolationCodeResolver;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @phpstan-import-type ApiErrors from ApiResponse
 */
final class ApiResponseErrorsMapper
{
    /**
     * @return ApiErrors
     */
    public static function fromViolations(
        ConstraintViolationListInterface $violations,
        ConstraintViolationCodeResolver $resolver,
    ): array {
        $items = [];

        foreach ($violations as $violation) {
            $code = $resolver->resolve($violation);
            $field = $violation->getPropertyPath();
            $key = $code->value.'|'.$field;

            if (isset($items[$key])) {
                continue;
            }

            $item = [
                'code' => $code->value,
                'message' => (string) $violation->getMessage(),
            ];

            if ('' !== $field) {
                $item['field'] = $field;
            }

            $items[$key] = $item;
        }

        $requiredFields = [];

        foreach ($items as $item) {
            if (ValidationErrorCode::FieldRequired->value === $item['code'] && isset($item['field'])) {
                $requiredFields[$item['field']] = true;
            }
        }

        foreach ($items as $key => $item) {
            if (ValidationErrorCode::FieldRequired->value !== $item['code']
                && isset($item['field'], $requiredFields[$item['field']])
            ) {
                unset($items[$key]);
            }
        }

        return array_values($items);
    }

    /**
     * @return ApiErrors
     */
    public static function fromDomainException(DomainException $exception): array
    {
        $details = $exception->errorDetails();

        if ([] === $details) {
            return [[
                'code' => $exception->errorCode(),
                'message' => $exception->publicMessage(),
            ]];
        }

        return array_map(
            static fn (array $detail): array => array_filter([
                'code' => $exception->errorCode(),
                'field' => $detail['field'] ?? null,
                'message' => $detail['message'] ?? $exception->publicMessage(),
            ], static fn (mixed $value): bool => null !== $value),
            $details,
        );
    }

    /**
     * Converte DomainError objects para o formato de resposta da API.
     *
     * @param list<DomainError> $errors
     *
     * @return ApiErrors
     */
    public static function fromDomainErrors(array $errors): array
    {
        $items = [];

        foreach ($errors as $error) {
            $item = [
                'code' => $error->code,
                'message' => $error->message,
            ];

            if (null !== $error->field) {
                $item['field'] = $error->field;
            }

            $items[$error->code.'|'.($error->field ?? '')] = $item;
        }

        return array_values($items);
    }
}
