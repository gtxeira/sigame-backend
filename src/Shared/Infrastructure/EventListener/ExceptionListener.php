<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventListener;

use App\Helpers\ApiResponse;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\UI\Http\ValidationErrorResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: 'kernel.exception')]
final class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof UnprocessableEntityHttpException
            && $exception->getPrevious() instanceof ValidationFailedException
        ) {
            /** @var ValidationFailedException $validationException */
            $validationException = $exception->getPrevious();
            $violations = $validationException->getViolations();

            $errors = ValidationErrorResponse::fromViolations($violations);

            $response = ApiResponse::error(
                errors: $errors,
            );

            $event->setResponse($response->toJsonResponse(Response::HTTP_UNPROCESSABLE_ENTITY));

            return;
        }

        if ($exception instanceof DomainException) {
            $errors = ValidationErrorResponse::fromDomainException($exception);

            $response = ApiResponse::error(
                errors: $errors,
            );

            $event->setResponse($response->toJsonResponse(Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }
}
