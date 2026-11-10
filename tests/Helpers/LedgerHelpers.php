<?php

declare(strict_types=1);

use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Gamification\Domain\Xp\Entity\XpEvent;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpEventId;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Domain\User\ValueObject\UserLevel;
use App\Shared\Domain\ValueObject\Email;

function ledgerTestUser(UserRepositoryInterface $userRepository, string $email): User
{
    $user = User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Ledger User',
    );
    $userRepository->save($user);

    return $user;
}

function ledgerTestRecordEvent(
    XpEventRepositoryInterface $xpEventRepository,
    PlayerId $userId,
    XpKind $kind,
    int $amount,
    string $idempotencyKey,
    ?XpEventId $reversalOfId = null,
): XpEvent {
    $event = XpEvent::record(
        userId: $userId,
        kind: $kind,
        amount: $amount,
        idempotencyKey: $idempotencyKey,
        reversalOfId: $reversalOfId,
    );
    $xpEventRepository->save($event);

    return $event;
}

function ledgerTestSyncLevel(UserRepositoryInterface $userRepository, User $user, int $level): void
{
    $user->syncLevel(UserLevel::fromInt($level)->value());
    $userRepository->save($user);
}

/**
 * Cria usuário, progresso e eventos coerentes entre si.
 *
 * @param list<array{kind: XpKind, amount: int}> $events
 *
 * @return array{user: User, playerId: PlayerId, progress: UserProgress}
 */
function ledgerTestUserWithEvents(
    UserRepositoryInterface $userRepository,
    UserProgressRepositoryInterface $progressRepository,
    XpEventRepositoryInterface $xpEventRepository,
    string $email,
    array $events,
): array {
    $user = ledgerTestUser($userRepository, $email);
    $playerId = PlayerId::fromString($user->id()->value());
    $progress = UserProgress::create($playerId);
    $balance = 0;
    $lifetime = 0;
    $index = 0;

    foreach ($events as $event) {
        ++$index;
        ledgerTestRecordEvent(
            $xpEventRepository,
            $playerId,
            $event['kind'],
            $event['amount'],
            sprintf('%s:%d', $email, $index),
        );

        $balance += $event['amount'];

        if ($event['kind']->affectsLifetime()) {
            $lifetime += $event['amount'];
        }
    }

    $progress->reconcile($balance, $lifetime);
    $progress->changeLevel((new LevelCurve())->levelFor($balance));
    $progressRepository->save($progress);
    ledgerTestSyncLevel($userRepository, $user, $progress->level());

    return [
        'user' => $user,
        'playerId' => $playerId,
        'progress' => $progress,
    ];
}

/**
 * @return array{user: UserRepositoryInterface, progress: UserProgressRepositoryInterface}
 */
function ledgerTestFunctionalRepositories(\Tests\FunctionalTestCase $test): array
{
    $container = $test->api->getKernelBrowser()->getContainer();

    return [
        /* @phpstan-ignore-next-line */
        'user' => $container->get(UserRepositoryInterface::class),
        /* @phpstan-ignore-next-line */
        'progress' => $container->get(UserProgressRepositoryInterface::class),
    ];
}

/**
 * Garante que `users.level` e `user_progress.level` acompanham o saldo.
 */
function ledgerTestAssertProjectionConsistent(\Tests\FunctionalTestCase $test, string $userId): void
{
    $repositories = ledgerTestFunctionalRepositories($test);
    $progress = $repositories['progress']->findByUserId(PlayerId::fromString($userId));
    $user = $repositories['user']->findById(UserId::fromString($userId));

    expect($progress)->not->toBeNull()
        ->and($user)->not->toBeNull()
        ->and($progress?->level())->toBe((new LevelCurve())->levelFor((int) $progress?->xpBalance()))
        ->and($user?->level()->value())->toBe($progress?->level());
}
