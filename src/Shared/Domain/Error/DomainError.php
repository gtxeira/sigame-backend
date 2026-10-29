<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * Objeto de erro estruturado, independente de framework.
 *
 * Representa uma falha de validação ou uma falha esperada de negócio.
 */
final readonly class DomainError
{
    public function __construct(
        public string $code,
        public string $message,
        public ErrorKind $kind,
        public ?string $field = null,
    ) {
    }
}
