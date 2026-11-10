<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Progress\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Identificador do usuário no contexto de gamificação.
 *
 * O módulo não importa o UserId de Identity; a igualdade é feita pelo UUID.
 */
final readonly class PlayerId extends BaseId
{
}
