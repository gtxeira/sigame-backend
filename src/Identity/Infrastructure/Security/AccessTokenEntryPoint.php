<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Presentation\Http\Response\ApiErrorCode;
use App\Shared\Presentation\Http\Response\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Resposta JSON 401 quando uma rota protegida é acessada sem access token.
 */
final class AccessTokenEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $code = $authException instanceof AccessTokenAuthenticationException
            ? $authException->apiErrorCode()
            : ApiErrorCode::AccessTokenMissing;

        $response = ApiResponse::error([
            [
                'code' => $code->value,
                'message' => $code->message(),
            ],
        ])->toJsonResponse(Response::HTTP_UNAUTHORIZED);

        $response->headers->set('WWW-Authenticate', AccessTokenChallenge::for($code));

        return $response;
    }
}
