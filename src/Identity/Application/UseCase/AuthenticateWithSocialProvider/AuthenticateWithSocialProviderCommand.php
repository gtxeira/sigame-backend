<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\AuthenticateWithSocialProvider;

/**
 * Command de login por authorization code de um provedor social.
 */
final readonly class AuthenticateWithSocialProviderCommand
{
    public function __construct(
        private string $provider,
        private string $authorizationCode,
        private string $redirectUri,
        private ?string $codeVerifier = null,
        private ?string $name = null,
    ) {
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function authorizationCode(): string
    {
        return $this->authorizationCode;
    }

    public function redirectUri(): string
    {
        return $this->redirectUri;
    }

    public function codeVerifier(): ?string
    {
        return $this->codeVerifier;
    }

    public function name(): ?string
    {
        return $this->name;
    }
}
