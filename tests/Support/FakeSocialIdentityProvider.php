<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Identity\Application\DTO\SocialIdentity;
use App\Identity\Application\Exception\SocialAuthenticationException;
use App\Identity\Application\Service\SocialIdentityProviderInterface;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;

/**
 * Provedor social determinístico para testes funcionais.
 *
 * O authorization code codifica a identidade:
 * - `email@example.com` → e-mail verificado;
 * - `unverified:email@example.com` → e-mail não verificado;
 * - `error:qualquer` → falha do provedor.
 */
final readonly class FakeSocialIdentityProvider implements SocialIdentityProviderInterface
{
    public function provider(): IdentityProvider
    {
        return IdentityProvider::GOOGLE;
    }

    public function fetchIdentity(
        string $authorizationCode,
        string $redirectUri,
        string $codeVerifier,
    ): SocialIdentity {
        if (str_starts_with($authorizationCode, 'error:')) {
            throw SocialAuthenticationException::providerRejected('google');
        }

        $verified = !str_starts_with($authorizationCode, 'unverified:');
        $email = $verified
            ? $authorizationCode
            : substr($authorizationCode, strlen('unverified:'));

        $emailResult = Email::fromString($email);

        if ($emailResult->isInvalid()) {
            throw SocialAuthenticationException::providerRejected('google');
        }

        return new SocialIdentity(
            provider: IdentityProvider::GOOGLE,
            subject: ProviderSubject::fromString('google:'.$emailResult->value()->value()),
            email: $emailResult->value(),
            emailVerified: $verified,
            name: 'Fake Social User',
        );
    }
}
