<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Exception;

use RuntimeException;
use Throwable;

/**
 * Conflito de unicidade em uma participação ativa do grupo.
 */
final class MembershipAlreadyExistsException extends RuntimeException
{
    public static function create(Throwable $previous): self
    {
        return new self(
            'Já existe uma participação pendente ou aprovada para este usuário no grupo.',
            previous: $previous,
        );
    }
}
