<?php

declare(strict_types=1);

namespace App\Identity\Domain\RefreshToken\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Value Object tipado para a família de refresh tokens de uma sessão.
 *
 * A família é preservada em cada rotação; o reuso de um token revogado revoga
 * todos os tokens da mesma família.
 */
final readonly class TokenFamilyId extends BaseId
{
}
