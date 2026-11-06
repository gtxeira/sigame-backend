<?php

declare(strict_types=1);

use App\Identity\Application\DTO\SocialFlow;
use App\Identity\Application\DTO\SocialFlowPurpose;
use App\Identity\Application\Service\SocialAuthorizationUrlBuilderInterface;
use App\Identity\Application\Service\SocialFlowStoreInterface;
use App\Identity\Application\Service\SocialIdentityProviderRegistry;
use App\Identity\Application\UseCase\Social\StartSocialFlow\StartSocialFlowCommand;
use App\Identity\Application\UseCase\Social\StartSocialFlow\StartSocialFlowHandler;
use App\Identity\Domain\User\ValueObject\UserId;
use Symfony\Component\Clock\MockClock;
use Tests\Support\FakeSocialIdentityProvider;

it('creates a login flow and returns the authorization url', function () {
    $builder = Mockery::mock(SocialAuthorizationUrlBuilderInterface::class);
    $builder->shouldReceive('redirectUri')->once()->andReturn('https://app.example.com/auth/google/callback');
    $builder->shouldReceive('authorizationUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/v2/auth?x=1');

    $store = Mockery::mock(SocialFlowStoreInterface::class);
    $store->shouldReceive('save')->once()->withArgs(
        static fn (SocialFlow $flow): bool => SocialFlowPurpose::Login === $flow->purpose
            && 'web' === $flow->client
            && null === $flow->userId,
    );

    $handler = new StartSocialFlowHandler(
        providerRegistry: new SocialIdentityProviderRegistry([new FakeSocialIdentityProvider()]),
        urlBuilder: $builder,
        flowStore: $store,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        flowTtlSeconds: 600,
    );

    $result = $handler(new StartSocialFlowCommand('google', 'web'));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->authorizationUrl)->toBe('https://accounts.google.com/o/oauth2/v2/auth?x=1')
        ->and($result->value()->state)->not->toBeEmpty()
        ->and($result->value()->flowId)->not->toBeEmpty()
        ->and($result->value()->expiresIn)->toBe(600);
});

it('creates a link flow when there is an authenticated user', function () {
    $builder = Mockery::mock(SocialAuthorizationUrlBuilderInterface::class);
    $builder->shouldReceive('redirectUri')->once()->andReturn('https://app.example.com/auth/google/callback');
    $builder->shouldReceive('authorizationUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/v2/auth?x=1');

    $userId = UserId::generate();
    $store = Mockery::mock(SocialFlowStoreInterface::class);
    $store->shouldReceive('save')->once()->withArgs(
        static fn (SocialFlow $flow): bool => SocialFlowPurpose::Link === $flow->purpose
            && null !== $flow->userId
            && $userId->equals($flow->userId),
    );

    $handler = new StartSocialFlowHandler(
        providerRegistry: new SocialIdentityProviderRegistry([new FakeSocialIdentityProvider()]),
        urlBuilder: $builder,
        flowStore: $store,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );

    $result = $handler(new StartSocialFlowCommand('google', 'web', $userId));

    expect($result->isSuccess())->toBeTrue();
});

it('rejects an unsupported provider', function () {
    $handler = new StartSocialFlowHandler(
        providerRegistry: new SocialIdentityProviderRegistry([new FakeSocialIdentityProvider()]),
        urlBuilder: Mockery::mock(SocialAuthorizationUrlBuilderInterface::class),
        flowStore: Mockery::mock(SocialFlowStoreInterface::class),
        clock: new MockClock(),
    );

    $result = $handler(new StartSocialFlowCommand('apple', 'web'));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_PROVIDER_UNSUPPORTED');
});

it('rejects an unsupported client', function () {
    $builder = Mockery::mock(SocialAuthorizationUrlBuilderInterface::class);
    $builder->shouldReceive('redirectUri')->once()->andReturn(null);

    $handler = new StartSocialFlowHandler(
        providerRegistry: new SocialIdentityProviderRegistry([new FakeSocialIdentityProvider()]),
        urlBuilder: $builder,
        flowStore: Mockery::mock(SocialFlowStoreInterface::class),
        clock: new MockClock(),
    );

    $result = $handler(new StartSocialFlowCommand('google', 'mobile'));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_CLIENT_UNSUPPORTED');
});
