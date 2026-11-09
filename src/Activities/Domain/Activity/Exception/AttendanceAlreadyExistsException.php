<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Exception;

use RuntimeException;
use Throwable;

/**
 * Conflito de unicidade de inscrição ativa, traduzido na fronteira do adapter.
 */
final class AttendanceAlreadyExistsException extends RuntimeException
{
    public static function create(Throwable $previous): self
    {
        return new self('Já existe uma inscrição ativa para esta atividade.', 0, $previous);
    }
}
