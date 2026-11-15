<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Application\Service\OtpCodeGeneratorInterface;
use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\Service\PasswordResetThrottleInterface;
use App\Identity\Application\UseCase\Password\RequestPasswordResetHandler;
use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpChallengeId;
use App\Identity\Domain\Otp\ValueObject\OtpCode;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

function passwordResetRequestUser(string $email = 'reset-request@example.com'): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Reset Request',
    );
}

function passwordResetRequestGenerator(string $code = '123456'): OtpCodeGeneratorInterface
{
    return new class($code) implements OtpCodeGeneratorInterface {
        public function __construct(private string $code)
        {
        }

        public function generate(): OtpCode
        {
            return OtpCode::fromGenerated($this->code);
        }
    };
}

function passwordResetRequestThrottle(?int $retryAfter = null): PasswordResetThrottleInterface
{
    $throttle = Mockery::mock(PasswordResetThrottleInterface::class);
    $throttle->shouldReceive('reserve')->zeroOrMoreTimes()->andReturn($retryAfter);

    return $throttle;
}

function passwordResetRequestHandler(
    UserRepositoryInterface $users,
    OtpChallengeRepositoryInterface $challenges,
    IdentityMailDispatcherInterface $dispatcher,
    LoggerInterface $logger,
    ?PasswordResetThrottleInterface $throttle = null,
    ?PasswordHasherInterface $hasher = null,
    ?MockClock $clock = null,
): RequestPasswordResetHandler {
    if (null === $hasher) {
        $hasher = Mockery::mock(PasswordHasherInterface::class);
        $hasher->shouldReceive('hash')->zeroOrMoreTimes()->andReturn(HashedPassword::fromHash('code-hash'));
    }

    return new RequestPasswordResetHandler(
        userRepository: $users,
        challengeRepository: $challenges,
        codeGenerator: passwordResetRequestGenerator(),
        passwordHasher: $hasher,
        mailDispatcher: $dispatcher,
        throttle: $throttle ?? passwordResetRequestThrottle(),
        logger: $logger,
        clock: $clock ?? new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        ttlSeconds: 600,
    );
}

it('does nothing for an invalid email', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('findByEmail');

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchPasswordReset');

    $throttle = Mockery::mock(PasswordResetThrottleInterface::class);
    $throttle->shouldNotReceive('reserve');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldNotReceive('hash');

    $handler = passwordResetRequestHandler(
        $users,
        $challenges,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
        throttle: $throttle,
        hasher: $hasher,
    );

    $outcome = $handler('not-an-email');

    expect($outcome->retryAfterSeconds)->toBeNull();
});

it('reserves the cooldown and hashes a dummy code when the account does not exist', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')
        ->once()
        ->with(Mockery::on(static fn (Email $email): bool => 'unknown@example.com' === $email->value()))
        ->andReturn(null);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchPasswordReset');

    $throttle = Mockery::mock(PasswordResetThrottleInterface::class);
    $throttle->shouldReceive('reserve')
        ->once()
        ->with(Mockery::on(static fn (Email $email): bool => 'unknown@example.com' === $email->value()))
        ->andReturn(null);

    // A KDF é executada mesmo sem conta, para o tempo não revelar o cadastro.
    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')
        ->once()
        ->with('123456')
        ->andReturn(HashedPassword::fromHash('dummy-hash'));

    $handler = passwordResetRequestHandler(
        $users,
        $challenges,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
        throttle: $throttle,
        hasher: $hasher,
    );

    $outcome = $handler('unknown@example.com');

    expect($outcome->retryAfterSeconds)->toBeNull();
});

it('treats a suspended account like an unknown one', function () {
    $user = passwordResetRequestUser();
    $user->suspend();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchPasswordReset');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->andReturn(HashedPassword::fromHash('dummy-hash'));

    $handler = passwordResetRequestHandler(
        $users,
        $challenges,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
        hasher: $hasher,
    );

    $outcome = $handler('reset-request@example.com');

    expect($outcome->retryAfterSeconds)->toBeNull();
});

it('invalidates previous challenges and sends a code valid for 10 minutes', function () {
    $user = passwordResetRequestUser();
    $saved = null;

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('invalidateActive')
        ->once()
        ->with($user, OtpPurpose::PasswordReset);
    $challenges->shouldReceive('save')->once()->with(Mockery::on(
        static function (OtpChallenge $challenge) use (&$saved): bool {
            $saved = $challenge;

            return true;
        },
    ));

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatched = null;
    $dispatcher->shouldReceive('dispatchPasswordReset')
        ->once()
        ->andReturnUsing(static function (
            Email $email,
            string $code,
            Instant $expiresAt,
            OtpChallengeId $challengeId,
        ) use (&$dispatched): void {
            $dispatched = [
                'email' => $email,
                'code' => $code,
                'expiresAt' => $expiresAt,
                'challengeId' => $challengeId,
            ];
        });

    $handler = passwordResetRequestHandler(
        $users,
        $challenges,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $outcome = $handler('reset-request@example.com');

    expect($outcome->retryAfterSeconds)->toBeNull()
        ->and($saved)->toBeInstanceOf(OtpChallenge::class)
        ->and($saved?->purpose())->toBe(OtpPurpose::PasswordReset)
        ->and($saved?->codeHash())->toBe('code-hash')
        ->and($dispatched['email']->value())->toBe('reset-request@example.com')
        ->and($dispatched['code'])->toBe('123456')
        ->and($dispatched['expiresAt']->equals(
            Instant::fromDateTime(new DateTimeImmutable('2026-01-01T00:10:00+00:00')),
        ))->toBeTrue()
        ->and($dispatched['challengeId']->equals($saved?->id()))->toBeTrue();
});

it('consumes the code when the dispatcher throws', function () {
    $user = passwordResetRequestUser();
    $saved = null;

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('invalidateActive')->once();
    $challenges->shouldReceive('save')->once()->with(Mockery::on(
        static function (OtpChallenge $challenge) use (&$saved): bool {
            $saved = $challenge;

            return true;
        },
    ));
    $challenges->shouldReceive('markConsumed')
        ->once()
        ->andReturn(true);

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatchPasswordReset')
        ->once()
        ->andThrow(new RuntimeException('smtp offline'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha ao enfileirar e-mail de reset de senha', Mockery::on(
            static fn (array $context): bool => 'smtp offline' === $context['reason'],
        ));

    $handler = passwordResetRequestHandler($users, $challenges, $dispatcher, $logger);

    $handler('reset-request@example.com');
});

it('returns the remaining cooldown without touching the account', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('findByEmail');

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('invalidateActive');
    $challenges->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchPasswordReset');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldNotReceive('hash');

    $handler = passwordResetRequestHandler(
        $users,
        $challenges,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
        throttle: passwordResetRequestThrottle(30),
        hasher: $hasher,
    );

    $outcome = $handler('unknown@example.com');

    expect($outcome->retryAfterSeconds)->toBe(30);
});
