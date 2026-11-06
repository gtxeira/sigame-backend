<?php

declare(strict_types=1);

use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\EntityManagerInterface;
use Tests\IntegrationTestCase;

function identityTokenRepositoryUser(IntegrationTestCase $test, string $email): User
{
    $user = User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Identity Token User',
    );

    $test->service(UserRepositoryInterface::class)->save($user);

    return $user;
}

function identityTokenRepositoryToken(
    User $user,
    string $raw,
    IdentityTokenPurpose $purpose = IdentityTokenPurpose::EmailVerification,
): IdentityToken {
    return IdentityToken::create(
        user: $user,
        purpose: $purpose,
        tokenHash: hash('sha256', $raw),
        expiresAt: Instant::fromDateTime((new DateTimeImmutable())->modify('+1 day')),
    );
}

it('persists only the token hash and reloads by hash', function () {
    $repository = $this->service(IdentityTokenRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = identityTokenRepositoryUser($this, 'identity-token@example.com');
    $token = identityTokenRepositoryToken($user, 'raw-identity-token');

    $repository->save($token);
    $em->clear();

    $found = $repository->findByHash(hash('sha256', 'raw-identity-token'));

    expect($found)->toBeInstanceOf(IdentityToken::class)
        ->and($found?->id()->equals($token->id()))->toBeTrue()
        ->and($found?->user()->id()->equals($user->id()))->toBeTrue()
        ->and($found?->purpose())->toBe(IdentityTokenPurpose::EmailVerification);

    $stored = $em->getConnection()->fetchOne(
        'SELECT token_hash FROM identity_tokens WHERE id = ?',
        [$token->id()->value()],
    );

    expect($stored)->toBe(hash('sha256', 'raw-identity-token'))
        ->and($stored)->not->toBe('raw-identity-token');
});

it('returns null for an unknown hash', function () {
    expect($this->service(IdentityTokenRepositoryInterface::class)->findByHash('unknown'))
        ->toBeNull();
});

it('consumes a token exactly once under concurrency semantics', function () {
    $repository = $this->service(IdentityTokenRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = identityTokenRepositoryUser($this, 'consume-once@example.com');
    $token = identityTokenRepositoryToken($user, 'raw-consume-once');

    $repository->save($token);

    expect($repository->markConsumed($token))->toBeTrue()
        ->and($repository->markConsumed($token))->toBeFalse();

    $em->clear();

    $found = $repository->findByHash(hash('sha256', 'raw-consume-once'));

    expect($found?->isConsumed())->toBeTrue();
});

it('invalidates every unconsumed token of a purpose but keeps consumed ones', function () {
    $repository = $this->service(IdentityTokenRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = identityTokenRepositoryUser($this, 'invalidate-all@example.com');

    $consumed = identityTokenRepositoryToken($user, 'raw-consumed');
    $repository->save($consumed);
    $repository->markConsumed($consumed);

    $firstOpen = identityTokenRepositoryToken($user, 'raw-open-1');
    $repository->save($firstOpen);

    $secondOpen = identityTokenRepositoryToken($user, 'raw-open-2');
    $repository->save($secondOpen);

    $otherPurpose = identityTokenRepositoryToken($user, 'raw-reset', IdentityTokenPurpose::PasswordReset);
    $repository->save($otherPurpose);

    $repository->invalidateUnconsumed($user, IdentityTokenPurpose::EmailVerification);
    $em->clear();

    expect($repository->findByHash(hash('sha256', 'raw-consumed'))?->isConsumed())->toBeTrue()
        ->and($repository->findByHash(hash('sha256', 'raw-open-1'))?->isConsumed())->toBeTrue()
        ->and($repository->findByHash(hash('sha256', 'raw-open-2'))?->isConsumed())->toBeTrue()
        ->and($repository->findByHash(hash('sha256', 'raw-reset'))?->isConsumed())->toBeFalse();
});
