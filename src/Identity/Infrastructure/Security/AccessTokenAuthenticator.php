<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Exception\InvalidTokenException;
use App\Identity\Application\Service\AccessTokenGeneratorInterface;
use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Error\IdentityErrors;
use App\Shared\Presentation\Http\Response\ApiErrorCode;
use App\Shared\Presentation\Http\Response\ApiResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticator stateless para `Authorization: Bearer <access_token>`.
 */
final class AccessTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AccessTokenGeneratorInterface $accessTokenGenerator,
        private readonly SecurityUserProvider $userProvider,
        #[Autowire(service: 'security.access_map')]
        private readonly AccessMapInterface $accessMap,
    ) {
    }

    public function supports(Request $request): bool
    {
        $header = $request->headers->get('Authorization');

        return null !== $header && str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $header = (string) $request->headers->get('Authorization');
        $rawToken = trim(substr($header, 7));

        if ('' === $rawToken) {
            throw new AccessTokenAuthenticationException(ApiErrorCode::AccessTokenMissing);
        }

        try {
            $claims = $this->accessTokenGenerator->parse($rawToken);
        } catch (InvalidTokenException $exception) {
            throw new AccessTokenAuthenticationException(
                $exception->isExpired()
                    ? ApiErrorCode::AccessTokenExpired
                    : ApiErrorCode::AccessTokenInvalid,
            );
        }

        return new SelfValidatingPassport(new UserBadge(
            userIdentifier: $claims->userId->value(),
            userLoader: fn (string $identifier) => $this->userProvider->loadUserByIdentifier($identifier),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Rotas públicas não dependem do Authorization. Um token velho no
        // cliente não pode impedir login, refresh, logout ou social start.
        if ($this->isPublicRequest($request)) {
            return null;
        }

        if ($exception instanceof AccountStatusException) {
            return ApiResponse::error([
                [
                    'code' => IdentityErrorCode::AccountSuspended->value,
                    'message' => IdentityErrors::accountSuspended()->message,
                ],
            ])->toJsonResponse(Response::HTTP_FORBIDDEN);
        }

        $code = $exception instanceof AccessTokenAuthenticationException
            ? $exception->apiErrorCode()
            : ApiErrorCode::AccessTokenInvalid;

        $response = ApiResponse::error([
            [
                'code' => $code->value,
                'message' => $code->message(),
            ],
        ])->toJsonResponse(Response::HTTP_UNAUTHORIZED);

        $response->headers->set('WWW-Authenticate', AccessTokenChallenge::for($code));

        return $response;
    }

    private function isPublicRequest(Request $request): bool
    {
        [$attributes] = $this->accessMap->getPatterns($request);

        return [AuthenticatedVoter::PUBLIC_ACCESS] === $attributes;
    }
}
