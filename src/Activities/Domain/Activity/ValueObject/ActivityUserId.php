<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Referência local ao usuário que participa ou opera uma atividade.
 */
final readonly class ActivityUserId extends BaseId
{
}
