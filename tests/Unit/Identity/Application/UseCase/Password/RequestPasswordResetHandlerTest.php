<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Application\UseCase\Password\RequestPasswordResetHandler;
use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
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

function passwordResetRequestHandler(
    UserRepositoryInterface $users,
    IdentityTokenRepositoryInterface $tokens,
    IdentityMailDispatcherInterface $dispatcher,
    LoggerInterface $logger,
    int $cooldownSeconds = 60,
    ?MockClock $clock = null,
): RequestPasswordResetHandler {
    return new RequestPasswordResetHandler(
        userRepository: $users,
        tokenRepository: $tokens,
        mailDispatcher: $dispatcher,
        logger: $logger,
        clock: $clock ?? new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        ttlSeconds: 1800,
        cooldownSeconds: $cooldownSeconds,
    );
}

it('does nothing for an invalid email', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('findByEmail');

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchPasswordReset');

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('not-an-email');
});

it('does nothing when the account does not exist', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn(null);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchPasswordReset');

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('unknown@example.com');
});

it('does nothing when the account is suspended', function () {
    $user = passwordResetRequestUser();
    $user->suspend();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldNotReceive('save');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchPasswordReset');

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('reset-request@example.com');
});

it('invalidates previous tokens and sends a reset token valid for 30 minutes', function () {
    $user = passwordResetRequestUser();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('hasUnconsumedCreatedAfter')
        ->once()
        ->with(
            $user,
            IdentityTokenPurpose::PasswordReset,
            Mockery::on(static fn (Instant $since): bool => $since->equals(
                Instant::fromDateTime(new DateTimeImmutable('2025-12-31T23:59:00+00:00')),
            )),
        )
        ->andReturn(false);

    $tokens->shouldReceive('invalidateUnconsumed')
        ->once()
        ->with($user, IdentityTokenPurpose::PasswordReset);

    $saved = null;
    $rawToken = null;

    $tokens->shouldReceive('save')->once()->with(Mockery::on(
        static function (IdentityToken $token) use (&$saved): bool {
            $saved = $token;

            return true;
        },
    ));

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatchPasswordReset')
        ->once()
        ->with(
            $user->email(),
            Mockery::on(static function (string $token) use (&$rawToken): bool {
                $rawToken = $token;

                return true;
            }),
            Mockery::on(static fn (Instant $expiresAt): bool => $expiresAt->equals(
                Instant::fromDateTime(new DateTimeImmutable('2026-01-01T00:30:00+00:00')),
            )),
            Mockery::on(static function (string $tokenHash) use (&$rawToken): bool {
                return hash('sha256', (string) $rawToken) === $tokenHash;
            }),
        );

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('reset-request@example.com');

    expect($saved)->toBeInstanceOf(IdentityToken::class)
        ->and($rawToken)->toBeString()->not->toBeEmpty()
        ->and($saved?->purpose())->toBe(IdentityTokenPurpose::PasswordReset)
        ->and($saved?->tokenHash())->toBe(hash('sha256', (string) $rawToken));
});

it('logs a warning instead of failing when the dispatcher throws', function () {
    $user = passwordResetRequestUser();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('hasUnconsumedCreatedAfter')->once()->andReturn(false);
    $tokens->shouldReceive('invalidateUnconsumed')->once();

    $saved = null;
    $tokens->shouldReceive('save')->once()->with(Mockery::on(
        static function (IdentityToken $token) use (&$saved): bool {
            $saved = $token;

            return true;
        },
    ));
    $tokens->shouldReceive('markConsumed')
        ->once()
        ->with(Mockery::on(static function (IdentityToken $token) use (&$saved): bool {
            return $token === $saved;
        }));

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

    $handler = passwordResetRequestHandler($users, $tokens, $dispatcher, $logger);

    $handler('reset-request@example.com');
});

it('uses the email from the resolved account instead of the request payload', function () {
    $user = passwordResetRequestUser('canonical@example.com');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')
        ->once()
        ->with(Mockery::on(static fn (Email $email): bool => 'other@example.com' === $email->value()))
        ->andReturn($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('hasUnconsumedCreatedAfter')->once()->andReturn(false);
    $tokens->shouldReceive('invalidateUnconsumed')->once();
    $tokens->shouldReceive('save')->once();

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatchPasswordReset')
        ->once()
        ->with(
            $user->email(),
            Mockery::type('string'),
            Mockery::type(Instant::class),
            Mockery::type('string'),
        );

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('other@example.com');
});

it('does not send a new token while the cooldown is active', function () {
    $user = passwordResetRequestUser();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('hasUnconsumedCreatedAfter')
        ->once()
        ->with(
            $user,
            IdentityTokenPurpose::PasswordReset,
            Mockery::on(static fn (Instant $since): bool => $since->equals(
                Instant::fromDateTime(new DateTimeImmutable('2025-12-31T23:59:00+00:00')),
            )),
        )
        ->andReturn(true);
    $tokens->shouldNotReceive('invalidateUnconsumed');
    $tokens->shouldNotReceive('save');
    $tokens->shouldNotReceive('markConsumed');

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchPasswordReset');

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('reset-request@example.com');
});

it('skips the cooldown check when it is disabled', function () {
    $user = passwordResetRequestUser();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldNotReceive('hasUnconsumedCreatedAfter');
    $tokens->shouldReceive('invalidateUnconsumed')->once();
    $tokens->shouldReceive('save')->once();

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatchPasswordReset')->once();

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $dispatcher,
        Mockery::mock(LoggerInterface::class),
        cooldownSeconds: 0,
    );

    $handler('reset-request@example.com');
});
