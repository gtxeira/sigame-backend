<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Application\DTO\SocialIdentity;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Porta para trocar o authorization code de um provedor social por uma identidade.
 *
 * Implementações são coletadas automaticamente pelo registry; adicionar um novo
 * provedor não exige alterar o handler nem o controller.
 */
#[AutoconfigureTag]
interface SocialIdentityProviderInterface
{
    public function provider(): IdentityProvider;

    /**
     * @throws \App\Identity\Application\Exception\SocialAuthenticationException
     */
    public function fetchIdentity(
        string $authorizationCode,
        string $redirectUri,
        ?string $codeVerifier = null,
        ?string $name = null,
    ): SocialIdentity;
}
