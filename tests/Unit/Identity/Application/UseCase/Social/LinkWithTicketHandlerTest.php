<?php

declare(strict_types=1);

use App\Identity\Application\DTO\LinkTicket;
use App\Identity\Application\Service\SocialLinkTicketStoreInterface;
use App\Identity\Application\UseCase\Social\LinkSocialIdentity\LinkWithTicketCommand;
use App\Identity\Application\UseCase\Social\LinkSocialIdentity\LinkWithTicketHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\Clock\MockClock;

/**
 * @return array{handler: LinkWithTicketHandler, repository: UserRepositoryInterface&\Mockery\MockInterface}
 */
function linkTicketHandler(
    string $ticketEmail = 'user@example.com',
): array {
    $clock = new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $ticket = new LinkTicket(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google:user@example.com'),
        email: Email::fromString($ticketEmail)->value(),
        expiresAt: Instant::fromDateTime($clock->now()->modify('+10 minutes')),
    );

    $tickets = Mockery::mock(SocialLinkTicketStoreInterface::class);
    $tickets->shouldReceive('consume')->once()->andReturn($ticket);

    /** @var UserRepositoryInterface&Mockery\MockInterface $repository */
    $repository = Mockery::mock(UserRepositoryInterface::class);

    $handler = new LinkWithTicketHandler(
        linkTicketStore: $tickets,
        userRepository: $repository,
        clock: $clock,
    );

    return compact('handler', 'repository');
}

it('links the identity when the ticket email matches the authenticated account', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('user@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'User',
    );
    ['handler' => $handler, 'repository' => $repository] = linkTicketHandler();

    $repository->shouldReceive('findById')->once()->andReturn($user);
    $repository->shouldReceive('findByProviderSubject')->once()->andReturn(null);
    $repository->shouldReceive('save')->once();

    $result = $handler(new LinkWithTicketCommand('google', 'ticket', $user->id()));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->identities())->toHaveCount(2);
});

it('rejects a ticket issued for another email', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('other@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Other',
    );
    ['handler' => $handler, 'repository' => $repository] = linkTicketHandler();

    $repository->shouldReceive('findById')->once()->andReturn($user);
    $repository->shouldNotReceive('save');

    $result = $handler(new LinkWithTicketCommand('google', 'ticket', $user->id()));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_LINK_EMAIL_MISMATCH');
});

it('rejects an identity already linked to another user', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('user@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'User',
    );
    $owner = User::registerWithProvider(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google:user@example.com'),
        email: Email::fromString('user@example.com')->value(),
        name: 'Owner',
    );
    ['handler' => $handler, 'repository' => $repository] = linkTicketHandler();

    $repository->shouldReceive('findById')->once()->andReturn($user);
    $repository->shouldReceive('findByProviderSubject')->once()->andReturn($owner);
    $repository->shouldNotReceive('save');

    $result = $handler(new LinkWithTicketCommand('google', 'ticket', $user->id()));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('SOCIAL_IDENTITY_ALREADY_LINKED');
});
