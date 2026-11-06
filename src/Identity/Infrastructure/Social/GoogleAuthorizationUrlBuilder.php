<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use App\Identity\Application\Service\SocialAuthorizationUrlBuilderInterface;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Monta a URL de autorização do Google e resolve o redirect URI por cliente.
 *
 * O redirect URI é sempre escolhido no servidor; o cliente só informa o tipo
 * (`web` para o front-end e `dev` para a página local de diagnóstico).
 */
final readonly class GoogleAuthorizationUrlBuilder implements SocialAuthorizationUrlBuilderInterface
{
    private const string AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const string SCOPE = 'openid email profile';

    public function __construct(
        #[Autowire(env: 'GOOGLE_CLIENT_ID')]
        private string $clientId,
        #[Autowire(env: 'GOOGLE_REDIRECT_URI_WEB')]
        private string $webRedirectUri,
        #[Autowire(env: 'default::GOOGLE_REDIRECT_URI_DEV')]
        private ?string $devRedirectUri,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
    ) {
    }

    public function redirectUri(IdentityProvider $provider, string $client): ?string
    {
        if (IdentityProvider::GOOGLE !== $provider) {
            return null;
        }

        if ('web' === $client) {
            return '' === $this->webRedirectUri ? null : $this->webRedirectUri;
        }

        if ('dev' === $client) {
            if ('dev' !== $this->environment || null === $this->devRedirectUri || '' === $this->devRedirectUri) {
                return null;
            }

            return $this->devRedirectUri;
        }

        return null;
    }

    public function authorizationUrl(
        IdentityProvider $provider,
        string $redirectUri,
        string $state,
        string $codeChallenge,
    ): string {
        if (IdentityProvider::GOOGLE !== $provider) {
            throw new InvalidArgumentException(sprintf('Provedor "%s" não suportado.', $provider->value));
        }

        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], encoding_type: PHP_QUERY_RFC3986);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }
}
