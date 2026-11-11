<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Validation;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Monta o erro `422` usado pelo `MapRequestPayload` a partir de uma lista de
 * itens `{code, field, message}`.
 *
 * Existe para validações que dependem do corpo bruto (presença de campo,
 * arquivo ausente) e não podem ser expressas só por constraints no DTO.
 */
final class RequestViolationFactory
{
    public static function unprocessable(string $code, string $field, string $message): UnprocessableEntityHttpException
    {
        return self::unprocessableMany([
            ['code' => $code, 'field' => $field, 'message' => $message],
        ]);
    }

    /**
     * @param list<array{code: string, field: string, message: string}> $errors
     */
    public static function unprocessableMany(array $errors): UnprocessableEntityHttpException
    {
        $violations = new ConstraintViolationList();

        foreach ($errors as $error) {
            $violations->add(new ConstraintViolation(
                message: $error['message'],
                messageTemplate: $error['message'],
                parameters: ['code' => $error['code']],
                root: null,
                propertyPath: $error['field'],
                invalidValue: null,
            ));
        }

        return new UnprocessableEntityHttpException(
            $errors[0]['message'] ?? 'Erro de validação.',
            new ValidationFailedException(null, $violations),
        );
    }
}
