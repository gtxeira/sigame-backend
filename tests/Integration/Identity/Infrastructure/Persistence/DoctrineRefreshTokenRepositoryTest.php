<?php

declare(strict_types=1);

use App\Identity\Domain\RefreshToken\Entity\RefreshToken;
use App\Identity\Domain\RefreshToken\Repository\RefreshTokenRepositoryInterface;
use App\Identity\Domain\RefreshToken\ValueObject\TokenFamilyId;
use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\EntityManagerInterface;
use Tests\IntegrationTestCase;

function refreshRepositoryUser(IntegrationTestCase $test, string $email): User
{
    $user = User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Refresh Repository User',
    );

    $test->service(UserRepositoryInterface::class)->save($user);

    return $user;
}

function refreshRepositoryToken(User $user, string $raw, ?TokenFamilyId $family = null): RefreshToken
{
    return RefreshToken::create(
        user: $user,
        tokenHash: TokenHash::fromRaw($raw),
        familyId: $family ?? TokenFamilyId::generate(),
        expiresAt: Instant::fromDateTime((new DateTimeImmutable())->modify('+1 day')),
    );
}

it('persists only the token hash and reloads by hash', function () {
    $repository = $this->service(RefreshTokenRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = refreshRepositoryUser($this, 'refresh-repo@example.com');
    $token = refreshRepositoryToken($user, 'rt_raw_value');

    $repository->save($token);
    $em->clear();

    $found = $repository->findByTokenHash(TokenHash::fromRaw('rt_raw_value'));

    assert($found instanceof RefreshToken);

    expect($found->id()->equals($token->id()))->toBeTrue()
        ->and($found->user()->id()->equals($user->id()))->toBeTrue();

    $stored = $em->getConnection()->fetchOne('SELECT token_hash FROM refresh_tokens WHERE id = ?', [$token->id()->value()]);

    expect($stored)->toBe(TokenHash::fromRaw('rt_raw_value')->value())
        ->and($stored)->not->toBe('rt_raw_value');
});

it('rotates a token atomically', function () {
    $repository = $this->service(RefreshTokenRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = refreshRepositoryUser($this, 'refresh-rotate@example.com');
    $family = TokenFamilyId::generate();
    $current = refreshRepositoryToken($user, 'rt_current', $family);
    $replacement = refreshRepositoryToken($user, 'rt_replacement', $family);

    $repository->save($current);
    $current->revoke($replacement);
    $repository->rotate($current, $replacement);
    $em->clear();

    $old = $repository->findByTokenHash(TokenHash::fromRaw('rt_current'));
    $new = $repository->findByTokenHash(TokenHash::fromRaw('rt_replacement'));

    assert($old instanceof RefreshToken);
    assert($new instanceof RefreshToken);

    expect($old->isRevoked())->toBeTrue()
        ->and($old->replacedBy()?->id()->equals($new->id()))->toBeTrue()
        ->and($new->isRevoked())->toBeFalse()
        ->and($new->familyId()->equals($family))->toBeTrue();
});

it('revokes every token of a family', function () {
    $repository = $this->service(RefreshTokenRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = refreshRepositoryUser($this, 'refresh-family@example.com');
    $family = TokenFamilyId::generate();
    $first = refreshRepositoryToken($user, 'rt_family_1', $family);
    $second = refreshRepositoryToken($user, 'rt_family_2', $family);

    $repository->save($first);
    $repository->save($second);
    $repository->revokeFamily($family);
    $em->clear();

    $reloadedFirst = $repository->findByTokenHash(TokenHash::fromRaw('rt_family_1'));
    $reloadedSecond = $repository->findByTokenHash(TokenHash::fromRaw('rt_family_2'));

    assert($reloadedFirst instanceof RefreshToken);
    assert($reloadedSecond instanceof RefreshToken);

    expect($reloadedFirst->isRevoked())->toBeTrue()
        ->and($reloadedSecond->isRevoked())->toBeTrue();
});

it('keeps only the newest active families', function () {
    $repository = $this->service(RefreshTokenRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = refreshRepositoryUser($this, 'refresh-limit@example.com');

    for ($i = 0; $i < 6; ++$i) {
        $repository->save(refreshRepositoryToken($user, 'rt_limit_'.$i));
    }

    $repository->keepNewestFamilies($user->id(), 5);
    $em->clear();

    $activeFamilies = $em->getConnection()->fetchOne(
        'SELECT COUNT(DISTINCT family_id) FROM refresh_tokens WHERE user_id = ? AND revoked_at IS NULL',
        [$user->id()->value()],
    );

    expect((int) $activeFamilies)->toBe(5);
});

it('revokes every token of a user', function () {
    $repository = $this->service(RefreshTokenRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = refreshRepositoryUser($this, 'refresh-all@example.com');
    $token = refreshRepositoryToken($user, 'rt_all');

    $repository->save($token);
    $repository->revokeAllForUser($user->id());
    $em->clear();

    $reloaded = $repository->findByTokenHash(TokenHash::fromRaw('rt_all'));

    assert($reloaded instanceof RefreshToken);

    expect($reloaded->isRevoked())->toBeTrue();
});
