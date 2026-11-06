<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Presentation\Http\Response\ApiErrorCode;

/**
 * Monta o header `WWW-Authenticate` conforme o erro de access token.
 */
final class AccessTokenChallenge
{
    public static function for(ApiErrorCode $code): string
    {
        return match ($code) {
            ApiErrorCode::AccessTokenInvalid,
            ApiErrorCode::AccessTokenExpired => 'Bearer realm="sigame-api", error="invalid_token"',
            default => 'Bearer realm="sigame-api"',
        };
    }
}
