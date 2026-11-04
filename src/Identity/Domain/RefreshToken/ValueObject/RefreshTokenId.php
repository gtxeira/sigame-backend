<?php

declare(strict_types=1);

namespace App\Identity\Domain\RefreshToken\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Value Object tipado para identificadores internos de refresh token.
 */
final readonly class RefreshTokenId extends BaseId
{
}
