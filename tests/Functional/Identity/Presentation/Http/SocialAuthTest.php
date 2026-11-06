<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * Inicia um fluxo e devolve state + cookie já aplicados ao browser de teste.
 *
 * @return array{state: string, flowId: string}
 */
function socialTestStart(FunctionalTestCase $test, string $client = 'web', ?string $accessToken = null): array
{
    $server = null === $accessToken ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $response = $test->api->post(
        'app_auth_social_start',
        ['client' => $client],
        ['provider' => 'google'],
        $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $body = authTestBody($response);
    $flowId = (string) authTestSocialFlowCookie($response);

    authTestSetSocialFlowCookie($test, $flowId);

    return [
        'state' => (string) $body['data']['state'],
        'flowId' => $flowId,
    ];
}

function socialTestUser(FunctionalTestCase $test, string $email): ?\App\Identity\Domain\User\Entity\User
{
    return authTestUserRepository($test)->findByEmail(Email::fromString($email)->value());
}

it('creates a verified user on the first social login', function () {
    $flow = socialTestStart($this);

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'new-social@example.com', 'state' => $flow['state']],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestRefreshCookie($response))->toBeString()->toStartWith('rt_');

    $user = socialTestUser($this, 'new-social@example.com');

    expect($user)->not->toBeNull()
        ->and($user?->isEmailVerified())->toBeTrue()
        ->and($user?->hasLocalPassword())->toBeFalse();
});

it('rejects a social login with an unverified email', function () {
    $flow = socialTestStart($this);

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'unverified:not-verified@example.com', 'state' => $flow['state']],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_EMAIL_NOT_VERIFIED']);
});

it('rejects a social login when the provider refuses the code', function () {
    $flow = socialTestStart($this);

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'error:invalid', 'state' => $flow['state']],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_AUTHENTICATION_FAILED']);
});

it('rejects a callback with a state that does not match the flow', function () {
    socialTestStart($this);

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'user@example.com', 'state' => 'wrong-state'],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_FLOW_INVALID']);
});

it('returns a link ticket instead of taking over an account with a local password', function () {
    authTestCreateUser($this, 'taken@example.com');

    $flow = socialTestStart($this);

    $response = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'taken@example.com', 'state' => $flow['state']],
        ['provider' => 'google'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_ACCOUNT_LINK_REQUIRED'])
        ->and(authTestRefreshCookie($response))->toBeNull();

    $body = authTestBody($response);

    expect($body['meta']['link']['token'])->toBeString()->not->toBeEmpty();

    $user = socialTestUser($this, 'taken@example.com');

    expect($user)->not->toBeNull()
        ->and($user?->identities())->toHaveCount(1);
});

it('links the social identity from the ticket after local login', function () {
    authTestCreateUser($this, 'link-me@example.com');

    $flow = socialTestStart($this);
    $callback = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'link-me@example.com', 'state' => $flow['state']],
        ['provider' => 'google'],
    );
    $linkToken = (string) authTestBody($callback)['meta']['link']['token'];
    $accessToken = authTestAccessToken(authTestLogin($this, 'link-me@example.com'));

    $link = $this->api->post(
        'app_auth_social_link',
        ['linkToken' => $linkToken],
        ['provider' => 'google'],
        ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($link->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

    $linked = authTestUserRepository($this)->findByProviderSubject(
        IdentityProvider::GOOGLE,
        ProviderSubject::fromString('google:link-me@example.com'),
    );

    expect($linked)->not->toBeNull()
        ->and($linked?->email()->value())->toBe('link-me@example.com');

    $reuse = $this->api->post(
        'app_auth_social_link',
        ['linkToken' => $linkToken],
        ['provider' => 'google'],
        ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($reuse->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_LINK_TOKEN_INVALID']);
});

it('rejects a link ticket issued for another account', function () {
    authTestCreateUser($this, 'ticket-owner@example.com');
    authTestCreateUser($this, 'other-user@example.com');

    $flow = socialTestStart($this);
    $callback = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'ticket-owner@example.com', 'state' => $flow['state']],
        ['provider' => 'google'],
    );
    $linkToken = (string) authTestBody($callback)['meta']['link']['token'];
    $accessToken = authTestAccessToken(authTestLogin($this, 'other-user@example.com'));

    $response = $this->api->post(
        'app_auth_social_link',
        ['linkToken' => $linkToken],
        ['provider' => 'google'],
        ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_LINK_EMAIL_MISMATCH']);
});

it('links a google identity from the settings flow', function () {
    authTestCreateUser($this, 'settings-link@example.com');
    $accessToken = authTestAccessToken(authTestLogin($this, 'settings-link@example.com'));

    $flow = socialTestStart($this, 'web', $accessToken);

    $response = $this->api->post(
        'app_me_identity_link',
        ['code' => 'other-google@example.com', 'state' => $flow['state']],
        ['provider' => 'google'],
        ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

    $linked = authTestUserRepository($this)->findByProviderSubject(
        IdentityProvider::GOOGLE,
        ProviderSubject::fromString('google:other-google@example.com'),
    );

    expect($linked)->not->toBeNull()
        ->and($linked?->email()->value())->toBe('settings-link@example.com');
});

it('rejects linking an identity that already belongs to another user', function () {
    authTestCreateUser($this, 'identity-owner@example.com');
    authTestCreateUser($this, 'identity-thief@example.com');

    $ownerFlow = socialTestStart($this);
    $callback = $this->api->post(
        'app_auth_social_callback',
        ['code' => 'identity-owner@example.com', 'state' => $ownerFlow['state']],
        ['provider' => 'google'],
    );
    $linkToken = (string) authTestBody($callback)['meta']['link']['token'];
    $ownerAccess = authTestAccessToken(authTestLogin($this, 'identity-owner@example.com'));

    $this->api->post(
        'app_auth_social_link',
        ['linkToken' => $linkToken],
        ['provider' => 'google'],
        ['HTTP_AUTHORIZATION' => 'Bearer '.$ownerAccess],
    );

    $thiefAccess = authTestAccessToken(authTestLogin($this, 'identity-thief@example.com'));
    $thiefFlow = socialTestStart($this, 'web', $thiefAccess);

    $response = $this->api->post(
        'app_me_identity_link',
        ['code' => 'identity-owner@example.com', 'state' => $thiefFlow['state']],
        ['provider' => 'google'],
        ['HTTP_AUTHORIZATION' => 'Bearer '.$thiefAccess],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['SOCIAL_IDENTITY_ALREADY_LINKED']);
});
