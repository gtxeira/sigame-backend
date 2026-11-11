<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Application\UseCase\Admin;

use App\Identity\Application\UseCase\Admin\GetUser\GetUserHandler;
use App\Identity\Application\UseCase\Admin\GetUser\GetUserQuery;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Email;
use Mockery as m;

function getUserTestUser(string $email): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('$2y$04$hash'),
        name: 'Get User Test User',
    );
}

it('returns the account found by id', function () {
    $user = getUserTestUser('detail@example.com');

    $users = m::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findById')->once()->with($user->id())->andReturn($user);

    $result = (new GetUserHandler($users))(new GetUserQuery($user->id()));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBe($user);
});

it('fails when the account does not exist', function () {
    $userId = UserId::generate();

    $users = m::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findById')->once()->with($userId)->andReturn(null);

    $result = (new GetUserHandler($users))(new GetUserQuery($userId));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('USER_NOT_FOUND');
});
