<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Application\Service\OtpCodeGeneratorInterface;
use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\UseCase\EmailVerification\EmailVerificationRequestOutcome;
use App\Identity\Application\UseCase\EmailVerification\RequestEmailVerificationHandler;
use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpChallengeId;
use App\Identity\Domain\Otp\ValueObject\OtpCode;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

function emailVerificationTestUser(string $email = 'verify@example.com'): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Verify User',
    );
}

function emailVerificationTestGenerator(string $code = '123456'): OtpCodeGeneratorInterface
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

it('returns early when the email is already verified', function () {
    $user = emailVerificationTestUser();
    $user->verifyEmail();

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('invalidateActive');
    $challenges->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchEmailVerification');

    $handler = new RequestEmailVerificationHandler(
        challengeRepository: $challenges,
        codeGenerator: emailVerificationTestGenerator(),
        passwordHasher: Mockery::mock(PasswordHasherInterface::class),
        mailDispatcher: $dispatcher,
        logger: Mockery::mock(LoggerInterface::class),
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        ttlSeconds: 900,
        cooldownSeconds: 0,
    );

    expect($handler($user))->toBe(EmailVerificationRequestOutcome::AlreadyVerified);
});

it('invalidates previous challenges and sends a code valid for the configured ttl', function () {
    $user = emailVerificationTestUser();
    $clock = new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $saved = null;

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('invalidateActive')
        ->once()
        ->with($user, OtpPurpose::EmailVerification);
    $challenges->shouldReceive('save')
        ->once()
        ->with(Mockery::on(static function (OtpChallenge $challenge) use (&$saved): bool {
            $saved = $challenge;

            return true;
        }));

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->with('123456')->andReturn(HashedPassword::fromHash('code-hash'));

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatched = null;
    $dispatcher->shouldReceive('dispatchEmailVerification')
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

    $handler = new RequestEmailVerificationHandler(
        challengeRepository: $challenges,
        codeGenerator: emailVerificationTestGenerator(),
        passwordHasher: $hasher,
        mailDispatcher: $dispatcher,
        logger: Mockery::mock(LoggerInterface::class),
        clock: $clock,
        ttlSeconds: 900,
        cooldownSeconds: 0,
    );

    $outcome = $handler($user);

    expect($outcome)->toBe(EmailVerificationRequestOutcome::Queued)
        ->and($saved)->toBeInstanceOf(OtpChallenge::class)
        ->and($saved?->purpose())->toBe(OtpPurpose::EmailVerification)
        ->and($saved?->codeHash())->toBe('code-hash')
        ->and($dispatched['email']->value())->toBe('verify@example.com')
        ->and($dispatched['code'])->toBe('123456')
        ->and($dispatched['expiresAt']->equals(
            Instant::fromDateTime(new DateTimeImmutable('2026-01-01T00:15:00+00:00')),
        ))->toBeTrue()
        ->and($dispatched['challengeId']->equals($saved?->id()))->toBeTrue();
});

it('does not send a new code while the cooldown is active', function () {
    $user = emailVerificationTestUser();
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('hasActiveCreatedAfter')
        ->once()
        ->with(
            $user,
            OtpPurpose::EmailVerification,
            Mockery::on(static fn (Instant $since): bool => $since->equals(
                Instant::fromDateTime(new DateTimeImmutable('2025-12-31T23:59:00+00:00')),
            )),
        )
        ->andReturn(true);
    $challenges->shouldNotReceive('invalidateActive');
    $challenges->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchEmailVerification');

    $handler = new RequestEmailVerificationHandler(
        challengeRepository: $challenges,
        codeGenerator: emailVerificationTestGenerator(),
        passwordHasher: Mockery::mock(PasswordHasherInterface::class),
        mailDispatcher: $dispatcher,
        logger: Mockery::mock(LoggerInterface::class),
        clock: new MockClock($now),
        ttlSeconds: 900,
        cooldownSeconds: 60,
    );

    expect($handler($user))->toBe(EmailVerificationRequestOutcome::Queued);
});

it('logs a warning instead of failing when the dispatcher throws', function () {
    $user = emailVerificationTestUser();

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('invalidateActive')->once();
    $challenges->shouldReceive('save')->once();

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->andReturn(HashedPassword::fromHash('code-hash'));

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatchEmailVerification')
        ->once()
        ->andThrow(new RuntimeException('smtp offline'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha ao enfileirar e-mail de verificação', Mockery::on(
            static fn (array $context): bool => 'smtp offline' === $context['reason'],
        ));

    $handler = new RequestEmailVerificationHandler(
        challengeRepository: $challenges,
        codeGenerator: emailVerificationTestGenerator(),
        passwordHasher: $hasher,
        mailDispatcher: $dispatcher,
        logger: $logger,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        ttlSeconds: 900,
        cooldownSeconds: 0,
    );

    expect($handler($user))->toBe(EmailVerificationRequestOutcome::Queued);
});
