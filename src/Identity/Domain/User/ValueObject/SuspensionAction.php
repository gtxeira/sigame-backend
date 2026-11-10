<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

/**
 * Ação registrada no histórico de status da conta.
 */
enum SuspensionAction: string
{
    case SUSPENDED = 'SUSPENDED';
    case REACTIVATED = 'REACTIVATED';
}
