<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\ExceptionListener;

use App\Shared\Presentation\Http\Response\ApiErrorCode;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Security\CookieOriginNotAllowedException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Listener para converter exceções HTTP (400, 401, 403, 404, 405, 415…)
 * em respostas de erro da API.
 *
 * Usa sempre uma mensagem pública fixa por código, nunca a mensagem
 * original da exceção, para não expor detalhes internos do framework.
 */
#[AsEventListener(event: 'kernel.exception', priority: 25)]
final class HttpExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        // Erros de validação são tratados por SymfonyValidatorExceptionListener,
        // que preserva a lista de violações em `details`.
        if (422 === $exception->getStatusCode()
            && $exception->getPrevious() instanceof ValidationFailedException
        ) {
            return;
        }

        $code = $exception instanceof CookieOriginNotAllowedException
            ? ApiErrorCode::OriginNotAllowed
            : $this->resolveCode($exception->getStatusCode());

        $response = ApiResponse::error([
            [
                'code' => $code->value,
                'message' => $code->message(),
            ],
        ])->toJsonResponse($exception->getStatusCode());

        foreach ($exception->getHeaders() as $name => $value) {
            $response->headers->set($name, is_array($value) ? $value : (string) $value);
        }

        $event->setResponse($response);
    }

    private function resolveCode(int $statusCode): ApiErrorCode
    {
        return match ($statusCode) {
            400 => ApiErrorCode::BadRequest,
            401 => ApiErrorCode::Unauthorized,
            403 => ApiErrorCode::Forbidden,
            404 => ApiErrorCode::NotFound,
            405 => ApiErrorCode::MethodNotAllowed,
            415 => ApiErrorCode::UnsupportedMediaType,
            422 => ApiErrorCode::ValidationError,
            429 => ApiErrorCode::TooManyRequests,
            500 => ApiErrorCode::InternalError,
            default => ApiErrorCode::HttpError,
        };
    }
}
