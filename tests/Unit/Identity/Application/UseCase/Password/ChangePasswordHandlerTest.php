<?php

declare(strict_types=1);

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Application\UseCase\Password\ChangePasswordHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;

function changePasswordTestUser(string $email = 'change@example.com'): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('current-hash'),
        name: 'Change Password',
    );
}

it('changes the password and revokes every session', function () {
    $user = changePasswordTestUser();
    $newHash = HashedPassword::fromHash('new-hash');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('save')->once()->with($user);

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')
        ->once()
        ->with('current-password', Mockery::type(HashedPassword::class))
        ->andReturn(true);
    $hasher->shouldReceive('hash')->once()->with('new-secret-123')->andReturn($newHash);

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldReceive('revokeAllSessions')->once()->with($user->id());

    $handler = new ChangePasswordHandler(
        userRepository: $users,
        passwordHasher: $hasher,
        tokenPairIssuer: $tokenPairIssuer,
    );

    $result = $handler($user, 'current-password', 'new-secret-123');

    expect($result->isSuccess())->toBeTrue()
        ->and($user->password()?->value())->toBe('new-hash');
});

it('rejects the wrong current password', function () {
    $user = changePasswordTestUser();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->once()->andReturn(false);
    $hasher->shouldNotReceive('hash');

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldNotReceive('revokeAllSessions');

    $handler = new ChangePasswordHandler(
        userRepository: $users,
        passwordHasher: $hasher,
        tokenPairIssuer: $tokenPairIssuer,
    );

    $result = $handler($user, 'wrong-password', 'new-secret-123');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_CURRENT_INVALID');
});

it('rejects the change when the account has no local password', function () {
    $user = User::registerWithProvider(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google:social-only@example.com'),
        email: Email::fromString('social-only@example.com')->value(),
        name: 'Social Only',
    );

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldNotReceive('verify');
    $hasher->shouldNotReceive('hash');

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldNotReceive('revokeAllSessions');

    $handler = new ChangePasswordHandler(
        userRepository: $users,
        passwordHasher: $hasher,
        tokenPairIssuer: $tokenPairIssuer,
    );

    $result = $handler($user, 'any-password', 'new-secret-123');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_CURRENT_INVALID')
        ->and($user->hasLocalPassword())->toBeFalse();
});
