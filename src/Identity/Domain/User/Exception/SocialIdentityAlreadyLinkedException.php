<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\Exception\IdentityException;
use App\Shared\Domain\Error\ErrorKind;
use Throwable;

/**
 * Conflito de unicidade de identidade social detectado pelo banco.
 *
 * Existe para traduzir uma violação de constraint (corrida entre requisições)
 * em uma falha de negócio previsível, sem vazar detalhes do Doctrine.
 */
final class SocialIdentityAlreadyLinkedException extends IdentityException
{
    public static function create(?Throwable $previous = null): self
    {
        return new self(IdentityErrors::socialIdentityAlreadyLinked()->message, $previous);
    }

    public function errorCode(): string
    {
        return IdentityErrorCode::SocialIdentityAlreadyLinked->value;
    }

    public function kind(): ErrorKind
    {
        return IdentityErrorCode::SocialIdentityAlreadyLinked->kind();
    }

    public function publicMessage(): string
    {
        return IdentityErrors::socialIdentityAlreadyLinked()->message;
    }

    public function errorDetails(): array
    {
        return [['field' => 'provider']];
    }
}
