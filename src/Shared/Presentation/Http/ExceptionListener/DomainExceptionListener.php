<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\ExceptionListener;

use App\Shared\Domain\Exception\DomainException;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\ApiResponseErrorsMapper;
use App\Shared\Presentation\Http\Response\ErrorStatusResolver;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Listener para converter exceções de DomainException em respostas HTTP.
 */
#[AsEventListener(event: 'kernel.exception', priority: 20)]
final class DomainExceptionListener
{
    public function __construct(
        private readonly ErrorStatusResolver $errorStatusResolver,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Exceções de domínio — converte usando os dados públicos da exceção
        if ($exception instanceof DomainException) {
            $errors = ApiResponseErrorsMapper::fromDomainException($exception);

            $response = ApiResponse::error(
                errors: $errors,
            );

            $event->setResponse(
                $response->toJsonResponse(
                    $this->errorStatusResolver->fromKind($exception->kind()),
                ),
            );
        }
    }
}
