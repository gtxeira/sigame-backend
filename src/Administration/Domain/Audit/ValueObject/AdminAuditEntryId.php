<?php

declare(strict_types=1);

namespace App\Administration\Domain\Audit\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Identificador de uma entrada da trilha de auditoria.
 */
final readonly class AdminAuditEntryId extends BaseId
{
}
