<?php

declare(strict_types=1);

use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpChallengeId;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Infrastructure\Mailer\Listener\IdentityEmailFailureListener;
use App\Identity\Infrastructure\Mailer\Message\SendEmailVerificationMessage;
use App\Identity\Infrastructure\Mailer\Message\SendPasswordResetMessage;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

function identityFailureTestChallenge(): OtpChallenge
{
    $user = User::register(
        cpf: null,
        email: Email::fromString('failure@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Failure User',
    );

    return OtpChallenge::create(
        user: $user,
        purpose: OtpPurpose::PasswordReset,
        codeHash: 'hashed-code',
        expiresAt: Instant::now(),
    );
}

function identityFailureTestEvent(object $message, bool $willRetry = false): WorkerMessageFailedEvent
{
    $event = new WorkerMessageFailedEvent(
        envelope: new Envelope($message),
        receiverName: 'async',
        error: new RuntimeException('smtp offline'),
    );

    if ($willRetry) {
        $event->setForRetry();
    }

    return $event;
}

it('consumes the reset challenge after a definitive failure', function () {
    $challenge = identityFailureTestChallenge();

    $repository = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $repository->shouldReceive('findById')
        ->once()
        ->with(Mockery::on(static fn (OtpChallengeId $id): bool => $id->equals($challenge->id())))
        ->andReturn($challenge);
    $repository->shouldReceive('markConsumed')
        ->once()
        ->with($challenge)
        ->andReturn(true);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha definitiva ao enviar e-mail de reset de senha', Mockery::type('array'));

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new SendPasswordResetMessage(
        email: 'failure@example.com',
        encryptedCode: 'encrypted',
        expiresAtTimestamp: 1767225600,
        challengeId: $challenge->id()->value(),
    )));
});

it('does not consume the reset challenge while a retry is still possible', function () {
    $repository = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $repository->shouldNotReceive('findById');
    $repository->shouldNotReceive('markConsumed');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('warning');
    $logger->shouldNotReceive('error');

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(
        new SendPasswordResetMessage(
            email: 'failure@example.com',
            encryptedCode: 'encrypted',
            expiresAtTimestamp: 1767225600,
            challengeId: OtpChallengeId::generate()->value(),
        ),
        willRetry: true,
    ));
});

it('keeps the verification challenge valid after a definitive failure', function () {
    $repository = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $repository->shouldNotReceive('findById');
    $repository->shouldNotReceive('markConsumed');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha definitiva ao enviar verificação de e-mail', Mockery::type('array'));

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new SendEmailVerificationMessage(
        email: 'failure@example.com',
        encryptedCode: 'encrypted',
        expiresAtTimestamp: 1767225600,
        challengeId: OtpChallengeId::generate()->value(),
    )));
});

it('logs when the challenge is no longer found', function () {
    $repository = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $repository->shouldReceive('findById')->once()->andReturn(null);
    $repository->shouldNotReceive('markConsumed');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')->once();

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new SendPasswordResetMessage(
        email: 'failure@example.com',
        encryptedCode: 'encrypted',
        expiresAtTimestamp: 1767225600,
        challengeId: OtpChallengeId::generate()->value(),
    )));
});

it('logs and does not break the worker when the repository fails', function () {
    $repository = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $repository->shouldReceive('findById')->once()->andThrow(new RuntimeException('database down'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')
        ->once()
        ->with('Falha ao consumir código após erro de envio de e-mail', Mockery::on(
            static fn (array $context): bool => 'database down' === $context['reason'],
        ));

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new SendPasswordResetMessage(
        email: 'failure@example.com',
        encryptedCode: 'encrypted',
        expiresAtTimestamp: 1767225600,
        challengeId: OtpChallengeId::generate()->value(),
    )));
});

it('ignores unknown messages', function () {
    $repository = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $repository->shouldNotReceive('findById');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('warning');
    $logger->shouldNotReceive('error');

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new stdClass()));
});
