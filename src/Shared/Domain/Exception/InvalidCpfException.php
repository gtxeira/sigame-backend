<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Exceção para CPF inválido.
 */
final class InvalidCpfException extends InvalidArgumentException
{
    public function errorCode(): string
    {
        return 'INVALID_CPF';
    }

    public function publicMessage(): string
    {
        return 'The provided CPF is not valid.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'cpf',
                'message' => 'CPF must be exactly 11 digits with valid check digits.',
            ],
        ];
    }
}
