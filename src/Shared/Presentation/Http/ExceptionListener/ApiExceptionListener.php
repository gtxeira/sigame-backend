<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\ExceptionListener;

use App\Shared\Presentation\Http\Response\ApiErrorCode;
use App\Shared\Presentation\Http\Response\ApiResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Listener para converter exceções inesperadas e/ou não tratadas em resposta HTTP.
 */
#[AsEventListener(event: 'kernel.exception')]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        // TODO: adicionar log das exceções
        $exception = $event->getThrowable();

        // Exceções inesperadas/não tratadas — 500 com mensagem genérica
        $response = ApiResponse::error(
            errors: [
                [
                    'code' => ApiErrorCode::InternalError->value,
                    'message' => 'Um erro interno ocorreu. Por favor, aguarde e tente novamente.',
                ],
            ],
        );

        $event->setResponse($response->toJsonResponse(Response::HTTP_INTERNAL_SERVER_ERROR));
    }
}
