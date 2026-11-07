<?php

declare(strict_types=1);

use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
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

function identityFailureTestToken(): IdentityToken
{
    $user = User::register(
        cpf: null,
        email: Email::fromString('failure@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Failure User',
    );

    return IdentityToken::create(
        user: $user,
        purpose: IdentityTokenPurpose::PasswordReset,
        tokenHash: hash('sha256', 'raw-token'),
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

it('consumes the reset token after a definitive failure', function () {
    $token = identityFailureTestToken();

    $repository = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $repository->shouldReceive('findByHash')
        ->once()
        ->with($token->tokenHash())
        ->andReturn($token);
    $repository->shouldReceive('markConsumed')
        ->once()
        ->with($token)
        ->andReturn(true);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha definitiva ao enviar e-mail de reset de senha', Mockery::type('array'));

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new SendPasswordResetMessage(
        email: 'failure@example.com',
        encryptedToken: 'encrypted',
        expiresAtTimestamp: 1767225600,
        tokenHash: $token->tokenHash(),
    )));
});

it('does not consume the reset token while a retry is still possible', function () {
    $repository = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $repository->shouldNotReceive('findByHash');
    $repository->shouldNotReceive('markConsumed');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('warning');
    $logger->shouldNotReceive('error');

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(
        new SendPasswordResetMessage(
            email: 'failure@example.com',
            encryptedToken: 'encrypted',
            expiresAtTimestamp: 1767225600,
            tokenHash: 'hash',
        ),
        willRetry: true,
    ));
});

it('keeps the verification token valid after a definitive failure', function () {
    $repository = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $repository->shouldNotReceive('findByHash');
    $repository->shouldNotReceive('markConsumed');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('Falha definitiva ao enviar verificação de e-mail', Mockery::type('array'));

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new SendEmailVerificationMessage(
        email: 'failure@example.com',
        encryptedToken: 'encrypted',
        expiresAtTimestamp: 1767225600,
        tokenHash: 'hash',
    )));
});

it('logs when the token is no longer found', function () {
    $repository = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $repository->shouldReceive('findByHash')->once()->andReturn(null);
    $repository->shouldNotReceive('markConsumed');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')->once();

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new SendPasswordResetMessage(
        email: 'failure@example.com',
        encryptedToken: 'encrypted',
        expiresAtTimestamp: 1767225600,
        tokenHash: 'hash',
    )));
});

it('logs and does not break the worker when the repository fails', function () {
    $repository = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $repository->shouldReceive('findByHash')->once()->andThrow(new RuntimeException('database down'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')
        ->once()
        ->with('Falha ao consumir token após erro de envio de e-mail', Mockery::on(
            static fn (array $context): bool => 'database down' === $context['reason'],
        ));

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new SendPasswordResetMessage(
        email: 'failure@example.com',
        encryptedToken: 'encrypted',
        expiresAtTimestamp: 1767225600,
        tokenHash: 'hash',
    )));
});

it('ignores unknown messages', function () {
    $repository = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $repository->shouldNotReceive('findByHash');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('warning');
    $logger->shouldNotReceive('error');

    $listener = new IdentityEmailFailureListener($repository, $logger);

    $listener(identityFailureTestEvent(new stdClass()));
});
