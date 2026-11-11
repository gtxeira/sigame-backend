<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Application\UseCase\Admin;

use App\Identity\Application\UseCase\Admin\ListUsers\ListUsersHandler;
use App\Identity\Application\UseCase\Admin\ListUsers\ListUsersQuery;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserPage;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSearchCriteria;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Domain\User\ValueObject\UserStatus;
use App\Shared\Domain\ValueObject\Email;
use Mockery as m;

function listUsersTestUser(string $email): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('$2y$04$hash'),
        name: 'List Users Test User',
    );
}

it('returns the page produced by the repository for the given criteria', function () {
    $user = listUsersTestUser('list@example.com');
    $page = new UserPage([$user], 1);
    $criteria = new UserSearchCriteria(
        page: 2,
        perPage: 10,
        query: 'list',
        status: UserStatus::ACTIVE,
        role: UserRole::USER,
    );

    $users = m::mock(UserRepositoryInterface::class);
    $users->shouldReceive('search')->once()->with($criteria)->andReturn($page);

    $result = (new ListUsersHandler($users))(new ListUsersQuery($criteria));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBe($page)
        ->and($result->value()->items)->toBe([$user])
        ->and($result->value()->total)->toBe(1);
});
