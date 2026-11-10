<?php

declare(strict_types=1);

namespace Tests\Integration\Identity\Infrastructure\Persistence;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Entity\UserSuspension;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSuspensionRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\SuspensionAction;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Tests\IntegrationTestCase;

function suspensionTestUser(IntegrationTestCase $test, string $email): User
{
    /** @var UserRepositoryInterface $users */
    $users = $test->service(UserRepositoryInterface::class);
    $user = User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('$2y$04$hash'),
        name: 'Suspension Test User',
    );
    $users->save($user);

    return $user;
}

it('persists and lists the suspension history in descending order', function () {
    $target = suspensionTestUser($this, 'target-history@example.com');
    $actor = suspensionTestUser($this, 'actor-history@example.com');
    $actor->grantAdminRole();

    /** @var UserRepositoryInterface $users */
    $users = $this->service(UserRepositoryInterface::class);
    $users->save($actor);

    /** @var UserSuspensionRepositoryInterface $history */
    $history = $this->service(UserSuspensionRepositoryInterface::class);

    $suspended = UserSuspension::record(
        user: $target,
        actor: $actor,
        action: SuspensionAction::SUSPENDED,
        reason: 'Motivo da suspensão.',
        occurredAt: Instant::now(),
    );
    $history->save($suspended);

    $reactivated = UserSuspension::record(
        user: $target,
        actor: $actor,
        action: SuspensionAction::REACTIVATED,
        reason: null,
        occurredAt: Instant::now(),
    );
    $history->save($reactivated);

    $items = $history->findByUser($target->id());

    expect($items)->toHaveCount(2)
        ->and($items[0]->action())->toBe(SuspensionAction::REACTIVATED)
        ->and($items[1]->action())->toBe(SuspensionAction::SUSPENDED)
        ->and($history->countByUser($target->id()))->toBe(2);
});
