<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailerInterface;
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
    IdentityMailerInterface $mailer,
    LoggerInterface $logger,
): RequestPasswordResetHandler {
    return new RequestPasswordResetHandler(
        userRepository: $users,
        tokenRepository: $tokens,
        mailer: $mailer,
        logger: $logger,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        ttlSeconds: 1800,
    );
}

it('does nothing for an invalid email', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('findByEmail');

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldNotReceive('save');

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldNotReceive('sendPasswordReset');

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $mailer,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('not-an-email');
});

it('does nothing when the account does not exist', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn(null);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldNotReceive('save');

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldNotReceive('sendPasswordReset');

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $mailer,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('unknown@example.com');
});

it('does nothing when the account is suspended', function () {
    $user = passwordResetRequestUser();
    $user->suspend('fraud');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldNotReceive('save');

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldNotReceive('sendPasswordReset');

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $mailer,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('reset-request@example.com');
});

it('invalidates previous tokens and sends a reset token valid for 30 minutes', function () {
    $user = passwordResetRequestUser();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
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

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldReceive('sendPasswordReset')
        ->once()
        ->with(
            Mockery::type(Email::class),
            Mockery::on(static function (string $token) use (&$rawToken): bool {
                $rawToken = $token;

                return true;
            }),
            Mockery::on(static fn (Instant $expiresAt): bool => $expiresAt->equals(
                Instant::fromDateTime(new DateTimeImmutable('2026-01-01T00:30:00+00:00')),
            )),
        );

    $handler = passwordResetRequestHandler(
        $users,
        $tokens,
        $mailer,
        Mockery::mock(LoggerInterface::class),
    );

    $handler('reset-request@example.com');

    expect($saved)->toBeInstanceOf(IdentityToken::class)
        ->and($rawToken)->toBeString()->not->toBeEmpty()
        ->and($saved?->purpose())->toBe(IdentityTokenPurpose::PasswordReset)
        ->and($saved?->tokenHash())->toBe(hash('sha256', (string) $rawToken));
});

it('logs a warning instead of failing when the mailer throws', function () {
    $user = passwordResetRequestUser();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('invalidateUnconsumed')->once();
    $tokens->shouldReceive('save')->once();

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldReceive('sendPasswordReset')
        ->once()
        ->andThrow(new RuntimeException('smtp offline'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha ao enviar e-mail de reset de senha', Mockery::on(
            static fn (array $context): bool => 'smtp offline' === $context['reason'],
        ));

    $handler = passwordResetRequestHandler($users, $tokens, $mailer, $logger);

    $handler('reset-request@example.com');
});
