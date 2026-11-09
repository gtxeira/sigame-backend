<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\Exception\IdentityException;
use App\Shared\Domain\Error\ErrorKind;
use Throwable;

/**
 * Conflito de unicidade de CPF detectado pelo banco.
 *
 * Existe para traduzir uma violação de constraint (corrida entre requisições)
 * em uma falha de negócio previsível, sem vazar detalhes do Doctrine.
 */
final class CpfAlreadyExistsException extends IdentityException
{
    public static function create(?Throwable $previous = null): self
    {
        return new self(IdentityErrors::cpfAlreadyExists()->message, $previous);
    }

    public function errorCode(): string
    {
        return IdentityErrorCode::UserCpfAlreadyExists->value;
    }

    public function kind(): ErrorKind
    {
        return IdentityErrorCode::UserCpfAlreadyExists->kind();
    }

    public function publicMessage(): string
    {
        return IdentityErrors::cpfAlreadyExists()->message;
    }

    public function errorDetails(): array
    {
        return [['field' => 'cpf']];
    }
}
