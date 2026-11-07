<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\ExceptionListener;

use App\Shared\Presentation\Http\Response\ApiErrorCode;
use App\Shared\Presentation\Http\Response\ApiResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Listener para converter exceções inesperadas e/ou não tratadas em resposta HTTP.
 */
#[AsEventListener(event: 'kernel.exception')]
final readonly class ApiExceptionListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $this->logger->error($exception->getMessage(), ['exception' => $exception]);

        $event->getThrowable()
            |> (fn ($exception) => $this->logger->error($exception->getMessage(), ['exception' => $exception]));

        // Exceções inesperadas/não tratadas — 500 com mensagem genérica
        $response = ApiResponse::error(
            errors: [
                [
                    'code' => ApiErrorCode::InternalError->value,
                    'message' => ApiErrorCode::InternalError->message(),
                ],
            ],
        );

        $event->setResponse($response->toJsonResponse(Response::HTTP_INTERNAL_SERVER_ERROR));
    }
}
