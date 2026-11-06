<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Domain\User\ValueObject\IdentityProvider;

/**
 * Monta a URL de autorização e resolve o redirect URI no servidor.
 *
 * O cliente informa apenas o tipo de cliente (`web`, `dev`); nunca envia um
 * redirect URI arbitrário.
 */
interface SocialAuthorizationUrlBuilderInterface
{
    /**
     * Retorna null quando o cliente não é suportado neste ambiente.
     */
    public function redirectUri(IdentityProvider $provider, string $client): ?string;

    public function authorizationUrl(
        IdentityProvider $provider,
        string $redirectUri,
        string $state,
        string $codeChallenge,
    ): string;
}
