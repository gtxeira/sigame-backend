<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Validation;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Monta o mesmo erro `422 VALIDATION_ERROR` usado pelo `MapRequestPayload`.
 *
 * Existe para validações que dependem do corpo bruto (presença de campo,
 * arquivo ausente) e não podem ser expressas só por constraints no DTO.
 */
final class RequestViolationFactory
{
    public static function unprocessable(string $field, string $message): UnprocessableEntityHttpException
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                message: $message,
                messageTemplate: $message,
                parameters: [],
                root: null,
                propertyPath: $field,
                invalidValue: null,
            ),
        ]);

        return new UnprocessableEntityHttpException($message, new ValidationFailedException(null, $violations));
    }
}
