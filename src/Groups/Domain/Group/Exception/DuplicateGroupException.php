<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Exception;

use RuntimeException;
use Throwable;

/**
 * Conflito de unicidade na criação de grupos do mesmo criador.
 */
final class DuplicateGroupException extends RuntimeException
{
    public static function create(Throwable $previous): self
    {
        return new self(
            'Já existe um grupo do mesmo criador com este nome e modalidade.',
            previous: $previous,
        );
    }
}
