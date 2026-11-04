<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

/**
 * Provedor de identidade usado para autenticar o usuário.
 *
 * `LOCAL` representa e-mail + senha. Provedores sociais são adicionados
 * implementando `SocialIdentityProviderInterface` e acrescentando um caso aqui;
 * a coluna no banco é textual, então não há migração para isso.
 */
enum IdentityProvider: string
{
    case LOCAL = 'local';
    case GOOGLE = 'google';

    public function isLocal(): bool
    {
        return self::LOCAL === $this;
    }

    public function isSocial(): bool
    {
        return !$this->isLocal();
    }
}
