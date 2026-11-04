<?php

declare(strict_types=1);

use App\Identity\Application\DTO\IssuedToken;
use App\Identity\Application\DTO\SocialIdentity;
use App\Identity\Application\DTO\TokenPair;
use App\Identity\Application\Service\SocialIdentityProviderInterface;
use App\Identity\Application\Service\SocialIdentityProviderRegistry;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Application\UseCase\AuthenticateWithSocialProvider\AuthenticateWithSocialProviderCommand;
use App\Identity\Application\UseCase\AuthenticateWithSocialProvider\AuthenticateWithSocialProviderHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Log\LoggerInterface;

function socialProvider(SocialIdentity $identity): SocialIdentityProviderInterface
{
    return new class($identity) implements SocialIdentityProviderInterface {
        public function __construct(
            private readonly SocialIdentity $identity,
        ) {
        }

        public function provider(): IdentityProvider
        {
            return IdentityProvider::GOOGLE;
        }

        public function fetchIdentity(
            string $authorizationCode,
            string $redirectUri,
            ?string $codeVerifier = null,
            ?string $name = null,
        ): SocialIdentity {
            return $this->identity;
        }
    };
}

function socialHandler(
    UserRepositoryInterface $repository,
    TokenPairIssuerInterface $issuer,
    SocialIdentity $identity,
): AuthenticateWithSocialProviderHandler {
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatch')->andReturnNull()->byDefault();

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->andReturnNull()->byDefault();

    return new AuthenticateWithSocialProviderHandler(
        userRepository: $repository,
        providerRegistry: new SocialIdentityProviderRegistry([socialProvider($identity)]),
        tokenPairIssuer: $issuer,
        eventDispatcher: $eventDispatcher,
        logger: $logger,
    );
}

function socialTokenPair(): TokenPair
{
    return new TokenPair(
        accessToken: new IssuedToken('access-token', Instant::now()),
        refreshToken: 'rt_raw',
        refreshExpiresAt: Instant::now(),
    );
}

it('creates a new user from a verified social identity', function () {
    $identity = new SocialIdentity(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google-sub'),
        email: Email::fromString('social@example.com')->value(),
        emailVerified: true,
        name: 'Social User',
    );

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findByProviderSubject')->once()->andReturn(null);
    $repository->shouldReceive('findByEmail')->once()->andReturn(null);
    $repository->shouldReceive('save')->once()->with(Mockery::type(User::class));

    $issuer = Mockery::mock(TokenPairIssuerInterface::class);
    $issuer->shouldReceive('issueForNewSession')->once()->andReturn(socialTokenPair());

    $handler = socialHandler($repository, $issuer, $identity);

    $result = $handler(new AuthenticateWithSocialProviderCommand(
        provider: 'google',
        authorizationCode: 'code',
        redirectUri: 'http://localhost:3000/auth/google/callback',
    ));

    expect($result->isSuccess())->toBeTrue();
});

it('links a verified social identity to an existing account by email', function () {
    $existing = User::register(
        cpf: null,
        email: Email::fromString('social@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Existing User',
    );

    $identity = new SocialIdentity(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google-sub'),
        email: Email::fromString('social@example.com')->value(),
        emailVerified: true,
    );

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findByProviderSubject')->once()->andReturn(null);
    $repository->shouldReceive('findByEmail')->once()->andReturn($existing);
    $repository->shouldReceive('save')->once()->with($existing);

    $issuer = Mockery::mock(TokenPairIssuerInterface::class);
    $issuer->shouldReceive('issueForNewSession')->once()->with($existing)->andReturn(socialTokenPair());

    $handler = socialHandler($repository, $issuer, $identity);

    $result = $handler(new AuthenticateWithSocialProviderCommand(
        provider: 'google',
        authorizationCode: 'code',
        redirectUri: 'http://localhost:3000/auth/google/callback',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($existing->identities())->toHaveCount(2)
        ->and($existing->hasIdentity(IdentityProvider::GOOGLE, ProviderSubject::fromString('google-sub')))->toBeTrue();
});

it('rejects a social identity with an unverified email', function () {
    $identity = new SocialIdentity(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google-sub'),
        email: Email::fromString('social@example.com')->value(),
        emailVerified: false,
    );

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldNotReceive('findByProviderSubject');
    $repository->shouldNotReceive('findByEmail');

    $issuer = Mockery::mock(TokenPairIssuerInterface::class);
    $issuer->shouldNotReceive('issueForNewSession');

    $handler = socialHandler($repository, $issuer, $identity);

    $result = $handler(new AuthenticateWithSocialProviderCommand(
        provider: 'google',
        authorizationCode: 'code',
        redirectUri: 'http://localhost:3000/auth/google/callback',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_EMAIL_NOT_VERIFIED');
});

it('rejects a provider that is not implemented', function () {
    $repository = Mockery::mock(UserRepositoryInterface::class);
    $issuer = Mockery::mock(TokenPairIssuerInterface::class);

    $handler = new AuthenticateWithSocialProviderHandler(
        userRepository: $repository,
        providerRegistry: new SocialIdentityProviderRegistry([]),
        tokenPairIssuer: $issuer,
        eventDispatcher: Mockery::mock(EventDispatcherInterface::class),
        logger: Mockery::mock(LoggerInterface::class),
    );

    $result = $handler(new AuthenticateWithSocialProviderCommand(
        provider: 'apple',
        authorizationCode: 'code',
        redirectUri: 'http://localhost:3000/auth/apple/callback',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_PROVIDER_UNSUPPORTED');
});
