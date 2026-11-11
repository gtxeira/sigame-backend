<?php

declare(strict_types=1);

use App\Identity\Application\UseCase\AssignBirthDate\AssignBirthDateCommand;
use App\Identity\Application\UseCase\AssignBirthDate\AssignBirthDateHandler;
use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserDeleted;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\BirthDate;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\Clock\MockClock;

function assignBirthDateUser(?string $birthDate = null): User
{
    $user = User::register(
        cpf: null,
        email: Email::fromString('birth-date@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Birth Date User',
    );

    if (null !== $birthDate) {
        $user->assignBirthDate(BirthDate::fromString($birthDate)->value());
    }

    return $user;
}

function assignBirthDateHandler(
    UserRepositoryInterface $users,
    EventDispatcherInterface $events,
    ?MockClock $clock = null,
): AssignBirthDateHandler {
    return new AssignBirthDateHandler(
        userRepository: $users,
        eventDispatcher: $events,
        clock: $clock ?? new MockClock(new DateTimeImmutable('2026-11-11T12:00:00+00:00')),
    );
}

it('defines the birth date of an adult account', function () {
    $user = assignBirthDateUser();
    $users = Mockery::mock(UserRepositoryInterface::class);

    $users->shouldReceive('findById')->once()->andReturn($user);
    $users->shouldReceive('save')
        ->once()
        ->with(Mockery::on(static fn (User $saved): bool => '1990-05-12' === $saved->birthDate()?->value()));

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $result = (assignBirthDateHandler($users, $events))(new AssignBirthDateCommand(
        userId: $user->id(),
        birthDate: '1990-05-12',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->birthDate()?->value())->toBe('1990-05-12');
});

it('is idempotent for the same birth date', function () {
    $user = assignBirthDateUser('1990-05-12');
    $users = Mockery::mock(UserRepositoryInterface::class);

    $users->shouldReceive('findById')->once()->andReturn($user);
    $users->shouldNotReceive('save');

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $result = (assignBirthDateHandler($users, $events))(new AssignBirthDateCommand(
        userId: $user->id(),
        birthDate: '1990-05-12',
    ));

    expect($result->isSuccess())->toBeTrue();
});

it('rejects changing an existing birth date', function () {
    $user = assignBirthDateUser('1990-05-12');
    $users = Mockery::mock(UserRepositoryInterface::class);

    $users->shouldReceive('findById')->once()->andReturn($user);
    $users->shouldReceive('save')->never();
    $users->shouldReceive('delete')->never();

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $result = (assignBirthDateHandler($users, $events))(new AssignBirthDateCommand(
        userId: $user->id(),
        birthDate: '1991-01-01',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe(IdentityErrorCode::UserBirthDateAlreadySet->value)
        ->and($user->birthDate()?->value())->toBe('1990-05-12');
});

it('removes an incomplete account under the minimum age', function () {
    $user = assignBirthDateUser();
    $userId = $user->id()->value();
    $users = Mockery::mock(UserRepositoryInterface::class);

    $users->shouldReceive('findById')->once()->andReturn($user);
    $users->shouldNotReceive('save');
    $users->shouldReceive('delete')
        ->once()
        ->with(Mockery::on(static fn (User $deleted): bool => $deleted === $user));

    $users->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(static fn (callable $operation): mixed => $operation());

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(static fn (UserDeleted $event): bool => $event->userId() === $userId));

    $result = (assignBirthDateHandler($users, $events))(new AssignBirthDateCommand(
        userId: $user->id(),
        birthDate: '2010-01-01',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe(IdentityErrorCode::UserUnderMinimumAge->value)
        ->and($user->birthDate())->toBeNull();
});

it('rejects an invalid birth date before loading the user', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('findById');

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $result = (assignBirthDateHandler($users, $events))(new AssignBirthDateCommand(
        userId: App\Identity\Domain\User\ValueObject\UserId::generate(),
        birthDate: '2010-02-30',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('INVALID_FORMAT');
});
