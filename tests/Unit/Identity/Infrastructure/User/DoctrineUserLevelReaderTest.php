<?php

declare(strict_types=1);

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Infrastructure\User\DoctrineUserLevelReader;
use App\Shared\Domain\ValueObject\Email;
use Mockery as m;

it('reads the current level of a user', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('level-reader@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password'),
        name: 'Level Reader',
    );

    $repository = m::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findById')
        ->once()
        ->with(m::on(static fn (UserId $id): bool => $id->equals($user->id())))
        ->andReturn($user);

    expect((new DoctrineUserLevelReader($repository))->levelOf($user->id()->value()))->toBe(1);
});

it('returns null when the user does not exist', function () {
    $repository = m::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findById')->once()->andReturnNull();

    expect((new DoctrineUserLevelReader($repository))->levelOf(UserId::generate()->value()))->toBeNull();
});

it('returns null for an invalid user identifier', function () {
    $repository = m::mock(UserRepositoryInterface::class);
    $repository->shouldNotReceive('findById');

    expect((new DoctrineUserLevelReader($repository))->levelOf('not-a-uuid'))->toBeNull();
});
