<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Application\Service\OtpCodeGeneratorInterface;
use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\UseCase\EmailVerification\RequestEmailVerificationHandler;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpCode;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserRegistered;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Identity\Infrastructure\Mailer\SendEmailVerificationOnUserRegistered;
use App\Shared\Domain\ValueObject\Email;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

function registeredListenerUser(bool $verified = false): User
{
    if ($verified) {
        return User::registerWithProvider(
            provider: IdentityProvider::GOOGLE,
            subject: ProviderSubject::fromString('google:listener@example.com'),
            email: Email::fromString('listener@example.com')->value(),
            name: 'Listener User',
        );
    }

    return User::register(
        cpf: null,
        email: Email::fromString('listener@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Listener User',
    );
}

function registeredListenerGenerator(): OtpCodeGeneratorInterface
{
    return new class implements OtpCodeGeneratorInterface {
        public function generate(): OtpCode
        {
            return OtpCode::fromGenerated('123456');
        }
    };
}

function registeredListener(
    UserRepositoryInterface $users,
    OtpChallengeRepositoryInterface $challenges,
    IdentityMailDispatcherInterface $dispatcher,
    LoggerInterface $logger,
): SendEmailVerificationOnUserRegistered {
    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->zeroOrMoreTimes()->andReturn(HashedPassword::fromHash('code-hash'));

    return new SendEmailVerificationOnUserRegistered(
        userRepository: $users,
        requestEmailVerificationHandler: new RequestEmailVerificationHandler(
            challengeRepository: $challenges,
            codeGenerator: registeredListenerGenerator(),
            passwordHasher: $hasher,
            mailDispatcher: $dispatcher,
            logger: $logger,
            clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
            ttlSeconds: 900,
            cooldownSeconds: 0,
        ),
        logger: $logger,
    );
}

it('requests verification when the registered user is not verified', function () {
    $user = registeredListenerUser();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findById')->once()->andReturn($user);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('invalidateActive')->once();
    $challenges->shouldReceive('save')->once();

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatchEmailVerification')->once();

    $listener = registeredListener(
        $users,
        $challenges,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $listener(UserRegistered::now($user->id()->value(), 'listener@example.com', null));
});

it('does nothing when the user already has a verified email', function () {
    $user = registeredListenerUser(verified: true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findById')->once()->andReturn($user);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchEmailVerification');

    $listener = registeredListener(
        $users,
        $challenges,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $listener(UserRegistered::now($user->id()->value(), 'listener@example.com', null));
});

it('does nothing when the user is not found', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findById')->once()->andReturn(null);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchEmailVerification');

    $listener = registeredListener(
        $users,
        $challenges,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $listener(UserRegistered::now(
        \App\Identity\Domain\User\ValueObject\UserId::generate()->value(),
        'listener@example.com',
        null,
    ));
});

it('logs a warning instead of breaking the registration when the lookup fails', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findById')->once()->andThrow(new RuntimeException('database down'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha ao iniciar verificação de e-mail', Mockery::on(
            static fn (array $context): bool => 'database down' === $context['reason'],
        ));

    $listener = registeredListener(
        $users,
        Mockery::mock(OtpChallengeRepositoryInterface::class),
        Mockery::mock(IdentityMailDispatcherInterface::class),
        $logger,
    );

    $listener(UserRegistered::now(
        \App\Identity\Domain\User\ValueObject\UserId::generate()->value(),
        'listener@example.com',
        null,
    ));
});
