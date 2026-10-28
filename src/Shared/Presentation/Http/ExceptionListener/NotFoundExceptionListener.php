<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\ExceptionListener;

use App\Shared\Presentation\Http\Response\ApiErrorCode;
use App\Shared\Presentation\Http\Response\ApiResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Listener para converter exceções de HttpNotFoundException em respostas HTTP.
 */
#[AsEventListener(event: 'kernel.exception', priority: 10)]
final class NotFoundExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof NotFoundHttpException) {
            $response = ApiResponse::error([
                [
                    'code' => ApiErrorCode::NotFound->value,
                    'message' => 'Recurso não encontrado.',
                ],
            ]);

            $event->setResponse($response->toJsonResponse(Response::HTTP_NOT_FOUND));
        }
    }
}
