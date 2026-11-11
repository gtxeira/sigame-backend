<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\ExceptionListener;

use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\ApiResponseErrorsMapper;
use App\Shared\Presentation\Http\Validation\ConstraintViolationCodeResolver;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Listener para converter exceções de validação (do Symfony/Validator) em respostas HTTP.
 */
#[AsEventListener(event: 'kernel.exception', priority: 30)]
final class SymfonyValidatorExceptionListener
{
    public function __construct(
        private readonly ConstraintViolationCodeResolver $codeResolver,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Erros de validação do Symfony (via MapRequestPayload)
        if ($exception instanceof UnprocessableEntityHttpException
            && $exception->getPrevious() instanceof ValidationFailedException
        ) {
            /** @var ValidationFailedException $validationException */
            $validationException = $exception->getPrevious();
            $errors = ApiResponseErrorsMapper::fromViolations(
                $validationException->getViolations(),
                $this->codeResolver,
            );

            $response = ApiResponse::error(errors: $errors);

            $event->setResponse($response->toJsonResponse(Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }
}
