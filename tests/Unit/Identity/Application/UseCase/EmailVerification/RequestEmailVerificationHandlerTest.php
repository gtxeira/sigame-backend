<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Identity\Application\UseCase\EmailVerification\RequestEmailVerificationHandler;
use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
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

it('returns early when the email is already verified', function () {
    $user = emailVerificationTestUser();
    $user->verifyEmail();

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldNotReceive('invalidateUnconsumed');
    $tokens->shouldNotReceive('save');

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldNotReceive('sendEmailVerification');

    $handler = new RequestEmailVerificationHandler(
        tokenRepository: $tokens,
        mailer: $mailer,
        logger: Mockery::mock(LoggerInterface::class),
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        ttlSeconds: 86400,
    );

    $handler($user);
});

it('invalidates previous tokens and sends a token valid for the configured ttl', function () {
    $user = emailVerificationTestUser();
    $clock = new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('invalidateUnconsumed')
        ->once()
        ->with($user, IdentityTokenPurpose::EmailVerification);

    $saved = null;
    $rawToken = null;

    $tokens->shouldReceive('save')
        ->once()
        ->with(Mockery::on(static function (IdentityToken $token) use (&$saved): bool {
            $saved = $token;

            return true;
        }));

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldReceive('sendEmailVerification')
        ->once()
        ->with(
            Mockery::on(static fn (Email $email): bool => 'verify@example.com' === $email->value()),
            Mockery::on(static function (string $token) use (&$rawToken): bool {
                $rawToken = $token;

                return true;
            }),
            Mockery::on(static fn (Instant $expiresAt): bool => $expiresAt->equals(
                Instant::fromDateTime(new DateTimeImmutable('2026-01-02T00:00:00+00:00')),
            )),
        );

    $handler = new RequestEmailVerificationHandler(
        tokenRepository: $tokens,
        mailer: $mailer,
        logger: Mockery::mock(LoggerInterface::class),
        clock: $clock,
        ttlSeconds: 86400,
    );

    $handler($user);

    expect($saved)->toBeInstanceOf(IdentityToken::class)
        ->and($rawToken)->toBeString()->not->toBeEmpty()
        ->and($saved?->purpose())->toBe(IdentityTokenPurpose::EmailVerification)
        ->and($saved?->tokenHash())->toBe(hash('sha256', (string) $rawToken));
});

it('logs a warning instead of failing when the mailer throws', function () {
    $user = emailVerificationTestUser();

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('invalidateUnconsumed')->once();
    $tokens->shouldReceive('save')->once();

    $mailer = Mockery::mock(IdentityMailerInterface::class);
    $mailer->shouldReceive('sendEmailVerification')
        ->once()
        ->andThrow(new RuntimeException('smtp offline'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha ao enviar e-mail de verificação', Mockery::on(
            static fn (array $context): bool => 'smtp offline' === $context['reason'],
        ));

    $handler = new RequestEmailVerificationHandler(
        tokenRepository: $tokens,
        mailer: $mailer,
        logger: $logger,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        ttlSeconds: 86400,
    );

    $handler($user);
});
