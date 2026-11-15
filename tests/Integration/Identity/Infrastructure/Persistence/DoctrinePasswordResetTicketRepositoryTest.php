<?php

declare(strict_types=1);

use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\PasswordReset\Entity\PasswordResetTicket;
use App\Identity\Domain\PasswordReset\Repository\PasswordResetTicketRepositoryInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\EntityManagerInterface;
use Tests\IntegrationTestCase;

function resetTicketRepositoryUser(IntegrationTestCase $test, string $email): User
{
    $user = User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Reset Ticket User',
    );

    $test->service(UserRepositoryInterface::class)->save($user);

    return $user;
}

function resetTicketRepositoryChallenge(IntegrationTestCase $test, User $user): OtpChallenge
{
    $challenge = OtpChallenge::create(
        user: $user,
        purpose: OtpPurpose::PasswordReset,
        codeHash: 'code-hash',
        expiresAt: Instant::fromDateTime((new DateTimeImmutable())->modify('+10 minutes')),
    );

    $test->service(OtpChallengeRepositoryInterface::class)->save($challenge);

    return $challenge;
}

it('persists only the ticket hash and reloads it', function () {
    $repository = $this->service(PasswordResetTicketRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = resetTicketRepositoryUser($this, 'ticket-repo@example.com');
    $challenge = resetTicketRepositoryChallenge($this, $user);
    $raw = str_repeat('a', 64);
    $ticket = PasswordResetTicket::create(
        user: $user,
        challenge: $challenge,
        tokenHash: hash('sha256', $raw),
        expiresAt: Instant::fromDateTime((new DateTimeImmutable())->modify('+5 minutes')),
    );

    $repository->save($ticket);
    $em->clear();

    $found = $repository->findByHash(hash('sha256', $raw));

    assert($found instanceof PasswordResetTicket);

    expect($found->id()->equals($ticket->id()))->toBeTrue()
        ->and($found->user()->id()->equals($user->id()))->toBeTrue();

    $stored = $em->getConnection()->fetchOne(
        'SELECT token_hash FROM identity_password_reset_tickets WHERE id = ?',
        [$ticket->id()->value()],
    );

    expect($stored)->toBe(hash('sha256', $raw))
        ->and($stored)->not->toBe($raw);
});

it('consumes the ticket atomically only once', function () {
    $repository = $this->service(PasswordResetTicketRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = resetTicketRepositoryUser($this, 'ticket-consume@example.com');
    $challenge = resetTicketRepositoryChallenge($this, $user);
    $ticket = PasswordResetTicket::create(
        user: $user,
        challenge: $challenge,
        tokenHash: hash('sha256', 'raw-ticket'),
        expiresAt: Instant::fromDateTime((new DateTimeImmutable())->modify('+5 minutes')),
    );

    $repository->save($ticket);

    expect($repository->markConsumed($ticket))->toBeTrue()
        ->and($repository->markConsumed($ticket))->toBeFalse();
});

it('invalidates every active ticket of the user', function () {
    $repository = $this->service(PasswordResetTicketRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = resetTicketRepositoryUser($this, 'ticket-invalidate@example.com');
    $challenge = resetTicketRepositoryChallenge($this, $user);
    $ticket = PasswordResetTicket::create(
        user: $user,
        challenge: $challenge,
        tokenHash: hash('sha256', 'raw-invalidate'),
        expiresAt: Instant::fromDateTime((new DateTimeImmutable())->modify('+5 minutes')),
    );

    $repository->save($ticket);
    $repository->invalidateActive($user);
    $em->clear();

    $found = $repository->findByHash(hash('sha256', 'raw-invalidate'));

    assert($found instanceof PasswordResetTicket);

    expect($found->isConsumed())->toBeTrue();
});
