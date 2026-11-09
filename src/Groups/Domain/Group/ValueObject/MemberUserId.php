<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Identificador do usuário referenciado pelo módulo Groups.
 *
 * O módulo não importa `Identity\UserId`; a referência cross-module é feita
 * pelo mesmo UUID, mas com um tipo próprio da fronteira.
 */
final readonly class MemberUserId extends BaseId
{
}
