<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Error\IdentityErrors;
use App\Shared\Domain\Error\ErrorKind;
use App\Shared\Domain\Exception\DomainException;
use Throwable;

/**
 * Conflito de unicidade de e-mail detectado pelo banco.
 *
 * Existe para traduzir uma violação de constraint (corrida entre requisições)
 * em uma falha de negócio previsível, sem vazar detalhes do Doctrine.
 */
final class EmailAlreadyExistsException extends DomainException
{
    public static function create(?Throwable $previous = null): self
    {
        return new self('Já existe um usuário com este e-mail.', $previous);
    }

    public function errorCode(): string
    {
        return IdentityErrorCode::EmailAlreadyExists->value;
    }

    public function kind(): ErrorKind
    {
        return ErrorKind::Conflict;
    }

    public function publicMessage(): string
    {
        return IdentityErrors::emailAlreadyExists()->message;
    }

    public function errorDetails(): array
    {
        return [['field' => 'email']];
    }
}
