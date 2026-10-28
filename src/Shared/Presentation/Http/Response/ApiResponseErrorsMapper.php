<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Response;

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Exception\DomainException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @phpstan-import-type ApiErrors from ApiResponse
 */
final class ApiResponseErrorsMapper
{
    /**
     * @return ApiErrors
     */
    public static function fromViolations(ConstraintViolationListInterface $violations): array
    {
        $details = [];

        foreach ($violations as $violation) {
            $details[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return [
            [
                'code' => ApiErrorCode::ValidationError->value,
                'message' => 'Um ou mais erros de validação foram encontrados.',
                'details' => $details,
            ],
        ];
    }

    /**
     * @return ApiErrors
     */
    public static function fromDomainException(DomainException $exception): array
    {
        return [
            [
                'code' => $exception->errorCode(),
                'message' => $exception->publicMessage(),
                'details' => $exception->errorDetails(),
            ],
        ];
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
        $grouped = [];

        foreach ($errors as $error) {
            $code = $error->code;

            if (!isset($grouped[$code])) {
                $grouped[$code] = [
                    'code' => $code,
                    'message' => $error->message,
                    'details' => [],
                ];
            }

            if (null !== $error->field) {
                $grouped[$code]['details'][] = [
                    'field' => $error->field,
                ];
            }
        }

        return array_values($grouped);
    }
}
