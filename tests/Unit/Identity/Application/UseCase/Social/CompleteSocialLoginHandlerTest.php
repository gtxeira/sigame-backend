<?php

declare(strict_types=1);

use App\Identity\Application\DTO\IssuedToken;
use App\Identity\Application\DTO\SocialFlow;
use App\Identity\Application\DTO\SocialFlowPurpose;
use App\Identity\Application\DTO\TokenPair;
use App\Identity\Application\Service\SocialFlowStoreInterface;
use App\Identity\Application\Service\SocialIdentityProviderRegistry;
use App\Identity\Application\Service\SocialLinkTicketStoreInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Application\UseCase\Social\CompleteSocialLogin\CompleteSocialLoginCommand;
use App\Identity\Application\UseCase\Social\CompleteSocialLogin\CompleteSocialLoginHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Exception\SocialIdentityAlreadyLinkedException;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Tests\Support\FakeSocialIdentityProvider;

/**
 * @return array{handler: CompleteSocialLoginHandler, repository: UserRepositoryInterface&\Mockery\MockInterface, tokens: TokenPairIssuerInterface&\Mockery\MockInterface, tickets: SocialLinkTicketStoreInterface&\Mockery\MockInterface, events: EventDispatcherInterface&\Mockery\MockInterface}
 */
function socialLoginHandler(string $state = 'state'): array
{
    $clock = new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $flow = new SocialFlow(
        id: 'flow-id',
        provider: IdentityProvider::GOOGLE,
        client: 'web',
        redirectUri: 'https://app.example.com/auth/google/callback',
        codeVerifier: 'verifier',
        purpose: SocialFlowPurpose::Login,
        userId: null,
        state: $state,
        expiresAt: Instant::fromDateTime($clock->now()->modify('+10 minutes')),
    );

    $flows = Mockery::mock(SocialFlowStoreInterface::class);
    $flows->shouldReceive('consume')->andReturn($flow);

    /** @var UserRepositoryInterface&Mockery\MockInterface $repository */
    $repository = Mockery::mock(UserRepositoryInterface::class);

    /** @var SocialLinkTicketStoreInterface&Mockery\MockInterface $tickets */
    $tickets = Mockery::mock(SocialLinkTicketStoreInterface::class);

    $tokens = Mockery::mock(TokenPairIssuerInterface::class);
    $tokens->shouldReceive('issueForNewSession')->andReturn(new TokenPair(
        new IssuedToken('access-token', Instant::fromDateTime($clock->now()->modify('+15 minutes'))),
        'rt_'.str_repeat('a', 64),
        Instant::fromDateTime($clock->now()->modify('+7 days')),
    ));

    /** @var EventDispatcherInterface&Mockery\MockInterface $events */
    $events = Mockery::mock(EventDispatcherInterface::class);

    /** @var LoggerInterface&Mockery\MockInterface $logger */
    $logger = Mockery::mock(LoggerInterface::class);

    $handler = new CompleteSocialLoginHandler(
        userRepository: $repository,
        providerRegistry: new SocialIdentityProviderRegistry([new FakeSocialIdentityProvider()]),
        flowStore: $flows,
        linkTicketStore: $tickets,
        tokenPairIssuer: $tokens,
        eventDispatcher: $events,
        logger: $logger,
        clock: $clock,
        linkTicketTtlSeconds: 600,
    );

    return compact('handler', 'repository', 'tokens', 'tickets', 'events');
}

it('creates a new verified user on social login', function () {
    ['handler' => $handler, 'repository' => $repository, 'events' => $events] = socialLoginHandler();

    $repository->shouldReceive('findByProviderSubject')->once()->andReturn(null);
    $repository->shouldReceive('findByEmail')->once()->andReturn(null);
    $repository->shouldReceive('save')->once();
    $events->shouldReceive('dispatch')->once();

    $result = $handler(new CompleteSocialLoginCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'created@example.com',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->tokens)->not->toBeNull();
});

it('returns a link ticket for an existing account with a local password', function () {
    ['handler' => $handler, 'repository' => $repository, 'tickets' => $tickets] = socialLoginHandler();

    $user = User::register(
        cpf: null,
        email: Email::fromString('taken@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Taken User',
    );

    $repository->shouldReceive('findByProviderSubject')->once()->andReturn(null);
    $repository->shouldReceive('findByEmail')->once()->andReturn($user);
    $repository->shouldNotReceive('save');
    $tickets->shouldReceive('save')->once();

    $result = $handler(new CompleteSocialLoginCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'taken@example.com',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->requiresLink())->toBeTrue()
        ->and($result->value()->linkTicket?->token)->toBeString()->not->toBeEmpty();
});

it('links automatically when the existing account has no local password', function () {
    ['handler' => $handler, 'repository' => $repository] = socialLoginHandler();

    $user = User::registerWithProvider(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('other-subject'),
        email: Email::fromString('social-only@example.com')->value(),
        name: 'Social Only',
    );

    $repository->shouldReceive('findByProviderSubject')->once()->andReturn(null);
    $repository->shouldReceive('findByEmail')->once()->andReturn($user);
    $repository->shouldReceive('save')->once();

    $result = $handler(new CompleteSocialLoginCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'social-only@example.com',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->tokens)->not->toBeNull();
});

it('returns a conflict when the social identity is linked concurrently on signup', function () {
    ['handler' => $handler, 'repository' => $repository] = socialLoginHandler();

    $repository->shouldReceive('findByProviderSubject')->once()->andReturn(null);
    $repository->shouldReceive('findByEmail')->once()->andReturn(null);
    $repository->shouldReceive('save')->once()->andThrow(SocialIdentityAlreadyLinkedException::create());

    $result = $handler(new CompleteSocialLoginCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'created@example.com',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_IDENTITY_ALREADY_LINKED');
});

it('returns a conflict when the social identity is linked concurrently during automatic link', function () {
    ['handler' => $handler, 'repository' => $repository] = socialLoginHandler();

    $user = User::registerWithProvider(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('other-subject'),
        email: Email::fromString('social-only@example.com')->value(),
        name: 'Social Only',
    );

    $repository->shouldReceive('findByProviderSubject')->once()->andReturn(null);
    $repository->shouldReceive('findByEmail')->once()->andReturn($user);
    $repository->shouldReceive('save')->once()->andThrow(SocialIdentityAlreadyLinkedException::create());

    $result = $handler(new CompleteSocialLoginCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'social-only@example.com',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_IDENTITY_ALREADY_LINKED');
});

it('rejects a login with an unverified provider email', function () {
    ['handler' => $handler] = socialLoginHandler();

    $result = $handler(new CompleteSocialLoginCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'state',
        authorizationCode: 'unverified:user@example.com',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_EMAIL_NOT_VERIFIED');
});

it('rejects a flow whose state does not match', function () {
    ['handler' => $handler] = socialLoginHandler();

    $result = $handler(new CompleteSocialLoginCommand(
        provider: 'google',
        flowId: 'flow-id',
        state: 'different-state',
        authorizationCode: 'user@example.com',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_FLOW_INVALID');
});
