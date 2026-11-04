<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Value Object tipado para identificadores de uma identidade de login.
 */
final readonly class UserIdentityId extends BaseId
{
}
