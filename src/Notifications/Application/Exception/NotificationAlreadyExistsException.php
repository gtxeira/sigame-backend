<?php

declare(strict_types=1);

namespace App\Notifications\Application\Exception;

use RuntimeException;

/**
 * Conflito de idempotência ao persistir uma notificação.
 *
 * O handler trata como sucesso, pois a notificação já existe.
 */
final class NotificationAlreadyExistsException extends RuntimeException
{
}
