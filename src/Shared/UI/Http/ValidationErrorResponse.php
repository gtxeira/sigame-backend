<?php

namespace App\Shared\UI\Http;

use App\Shared\Domain\Exception\DomainException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ValidationErrorResponse
{
    /**
     * @return list<array{code: string, message: string, details: list<array{field: string, message: string}>}>
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
                'code' => 'VALIDATION_ERROR',
                'message' => 'Um ou mais erros de validação foram encontrados.',
                'details' => $details,
            ],
        ];
    }

    /**
     * @return list<array{code: string, message: string, details: list<array{field: string, message: string}>}>
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
}
