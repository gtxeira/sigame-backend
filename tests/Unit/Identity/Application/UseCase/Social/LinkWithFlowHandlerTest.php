<?php

declare(strict_types=1);

use App\Identity\Application\DTO\SocialFlow;
use App\Identity\Application\DTO\SocialFlowPurpose;
use App\Identity\Application\Service\SocialFlowStoreInterface;
use App\Identity\Application\Service\SocialIdentityProviderRegistry;
use App\Identity\Application\UseCase\Social\LinkSocialIdentity\LinkWithFlowCommand;
use App\Identity\Application\UseCase\Social\LinkSocialIdentity\LinkWithFlowHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Tests\Support\FakeSocialIdentityProvider;

function linkFlowTestFlow(
    ?string $userId = null,
    string $state = 'state',
    string $expiresAt = '2026-01-01T00:10:00+00:00',
    SocialFlowPurpose $purpose = SocialFlowPurpose::Link,
    IdentityProvider $provider = IdentityProvider::GOOGLE,
): SocialFlow {
    return new SocialFlow(
        id: 'flow-id',
        provider: $provider,
        client: 'web',
        redirectUri: 'https://app.example.com/auth/google/callback',
        codeVerifier: 'verifier',
        purpose: $purpose,
        userId: null === $userId ? null : \App\Identity\Domain\User\ValueObject\UserId::fromString($userId),
        state: $state,
        expiresAt: Instant::fromDateTime(new DateTimeImmutable($expiresAt)),
    );
}

/**
 * @return array{handler: LinkWithFlowHandler, users: UserRepositoryInterface&Mockery\MockInterface, flows: SocialFlowStoreInterface&Mockery\MockInterface}
 */
function linkFlowTestHandler(): array
{
    /** @var SocialFlowStoreInterface&Mockery\MockInterface $flows */
    $flows = Mockery::mock(SocialFlowStoreInterface::class);

    /** @var UserRepositoryInterface&Mockery\MockInterface $users */
    $users = Mockery::mock(UserRepositoryInterface::class);

    $handler = new LinkWithFlowHandler(
        flowStore: $flows,
        providerRegistry: new SocialIdentityProviderRegistry([new FakeSocialIdentityProvider()]),
        userRepository: $users,
        logger: Mockery::mock(LoggerInterface::class),
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );

    return compact('handler', 'users', 'flows');
}

function linkFlowTestUser(string $email = 'settings@example.com'): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Settings User',
    );
}

it('links the identity when the flow belongs to the authenticated user', function () {
    $user = linkFlowTestUser();
    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->with('flow-id')->andReturn(
        linkFlowTestFlow(userId: $user->id()->value()),
    );
    $users->shouldReceive('findById')->once()->withArgs(
        static fn ($id): bool => $user->id()->equals($id),
    )->andReturn($user);
    $users->shouldReceive('findByProviderSubject')->once()->andReturn(null);
    $users->shouldReceive('save')->once()->with($user);

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->identities())->toHaveCount(2);
});

it('is idempotent when the identity is already linked to the same user', function () {
    $user = linkFlowTestUser();
    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->andReturn(
        linkFlowTestFlow(userId: $user->id()->value()),
    );
    $users->shouldReceive('findById')->once()->andReturn($user);
    $users->shouldReceive('findByProviderSubject')->once()->andReturn($user);
    $users->shouldNotReceive('save');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->identities())->toHaveCount(1);
});

it('rejects a flow that belongs to another user', function () {
    $user = linkFlowTestUser();
    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->andReturn(
        linkFlowTestFlow(userId: \App\Identity\Domain\User\ValueObject\UserId::generate()->value()),
    );
    $users->shouldNotReceive('findById');
    $users->shouldNotReceive('save');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_FLOW_INVALID');
});

it('rejects an expired flow', function () {
    $user = linkFlowTestUser();
    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->andReturn(
        linkFlowTestFlow(
            userId: $user->id()->value(),
            expiresAt: '2025-12-31T23:59:59+00:00',
        ),
    );
    $users->shouldNotReceive('save');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_FLOW_INVALID');
});

it('rejects a state that does not match the flow', function () {
    $user = linkFlowTestUser();
    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->andReturn(
        linkFlowTestFlow(userId: $user->id()->value(), state: 'other-state'),
    );
    $users->shouldNotReceive('save');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_FLOW_INVALID');
});

it('rejects a login flow used on the link endpoint', function () {
    $user = linkFlowTestUser();
    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->andReturn(
        linkFlowTestFlow(userId: $user->id()->value(), purpose: SocialFlowPurpose::Login),
    );
    $users->shouldNotReceive('save');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_FLOW_INVALID');
});

it('rejects a flow consumed twice', function () {
    $user = linkFlowTestUser();
    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->andReturn(null);
    $users->shouldNotReceive('save');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_FLOW_INVALID');
});

it('rejects an unsupported provider', function () {
    ['handler' => $handler, 'flows' => $flows] = linkFlowTestHandler();
    $flows->shouldNotReceive('consume');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'apple',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: \App\Identity\Domain\User\ValueObject\UserId::generate(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_PROVIDER_UNSUPPORTED');
});

it('rejects an unverified provider email', function () {
    $user = linkFlowTestUser();
    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->andReturn(
        linkFlowTestFlow(userId: $user->id()->value()),
    );
    $users->shouldNotReceive('save');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'unverified:linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_EMAIL_NOT_VERIFIED');
});

it('rejects a link for a suspended account', function () {
    $user = linkFlowTestUser();
    $user->suspend('fraud');

    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->andReturn(
        linkFlowTestFlow(userId: $user->id()->value()),
    );
    $users->shouldReceive('findById')->once()->andReturn($user);
    $users->shouldNotReceive('save');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('ACCOUNT_SUSPENDED');
});

it('rejects an identity already linked to another user', function () {
    $user = linkFlowTestUser();
    $owner = User::registerWithProvider(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google:linked@example.com'),
        email: Email::fromString('owner@example.com')->value(),
        name: 'Owner',
    );

    ['handler' => $handler, 'users' => $users, 'flows' => $flows] = linkFlowTestHandler();

    $flows->shouldReceive('consume')->once()->andReturn(
        linkFlowTestFlow(userId: $user->id()->value()),
    );
    $users->shouldReceive('findById')->once()->andReturn($user);
    $users->shouldReceive('findByProviderSubject')->once()->andReturn($owner);
    $users->shouldNotReceive('save');

    $result = $handler(new LinkWithFlowCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'linked@example.com',
        userId: $user->id(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_IDENTITY_ALREADY_LINKED');
});
