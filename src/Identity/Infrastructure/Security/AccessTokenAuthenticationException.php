<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Presentation\Http\Response\ApiErrorCode;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Falha de autenticação do access token já classificada para a resposta HTTP.
 */
final class AccessTokenAuthenticationException extends AuthenticationException
{
    public function __construct(private readonly ApiErrorCode $apiErrorCode)
    {
        parent::__construct($apiErrorCode->message());
    }

    public function apiErrorCode(): ApiErrorCode
    {
        return $this->apiErrorCode;
    }
}
