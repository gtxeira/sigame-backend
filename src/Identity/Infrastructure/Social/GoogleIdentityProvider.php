<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use App\Identity\Application\DTO\SocialIdentity;
use App\Identity\Application\Exception\SocialAuthenticationException;
use App\Identity\Application\Service\SocialIdentityProviderInterface;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Troca o authorization code do Google por uma identidade.
 *
 * A validação do id_token usa o JWKS do Google. O redirect URI e o PKCE vêm
 * do fluxo mantido no servidor; nunca do corpo da requisição.
 */
final readonly class GoogleIdentityProvider implements SocialIdentityProviderInterface
{
    private const string TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const string JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const array ALLOWED_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private IdTokenVerifierInterface $idTokenVerifier,
        #[Autowire(env: 'GOOGLE_CLIENT_ID')]
        private string $clientId,
        #[Autowire(env: 'GOOGLE_CLIENT_SECRET')]
        private string $clientSecret,
        #[Autowire(param: 'kernel.environment')]
        private string $environment = 'dev',
    ) {
        if ('prod' === $this->environment && (str_starts_with($this->clientId, 'change-me') || str_starts_with($this->clientSecret, 'change-me'))) {
            throw new InvalidArgumentException('GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET não podem usar os valores padrão em produção.');
        }
    }

    public function provider(): IdentityProvider
    {
        return IdentityProvider::GOOGLE;
    }

    public function fetchIdentity(
        string $authorizationCode,
        string $redirectUri,
        string $codeVerifier,
    ): SocialIdentity {
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_ENDPOINT, [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 5,
                'max_duration' => 10,
                'body' => [
                    'code' => $authorizationCode,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                    'code_verifier' => $codeVerifier,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                throw SocialAuthenticationException::providerRejected('google');
            }

            /** @var array<string, mixed> $payload */
            $payload = $response->toArray();
            $idToken = $payload['id_token'] ?? null;

            if (!is_string($idToken) || '' === $idToken) {
                throw SocialAuthenticationException::providerRejected('google');
            }

            $claims = $this->idTokenVerifier->verify(
                idToken: $idToken,
                jwksUrl: self::JWKS_URL,
                audience: $this->clientId,
                allowedIssuers: self::ALLOWED_ISSUERS,
            );
        } catch (SocialAuthenticationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SocialAuthenticationException('Falha ao autenticar com o Google.', 0, $exception);
        }

        $subject = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;

        if (!is_string($subject) || '' === $subject || !is_string($email) || '' === $email) {
            throw SocialAuthenticationException::providerRejected('google');
        }

        $emailResult = Email::fromString($email);

        if ($emailResult->isInvalid()) {
            throw SocialAuthenticationException::providerRejected('google');
        }

        return new SocialIdentity(
            provider: IdentityProvider::GOOGLE,
            subject: ProviderSubject::fromString($subject),
            email: $emailResult->value(),
            emailVerified: filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            name: is_string($claims['name'] ?? null) ? mb_substr($claims['name'], 0, 255) : null,
            pictureUrl: is_string($claims['picture'] ?? null) ? $claims['picture'] : null,
        );
    }
}
