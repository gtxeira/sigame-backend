<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Identificador do registro de histórico de suspensão/reativação.
 */
final readonly class UserSuspensionId extends BaseId
{
}
