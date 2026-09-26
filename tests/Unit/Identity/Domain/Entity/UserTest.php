<?php

declare(strict_types=1);

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Exception\OnboardingAlreadyCompletedException;
use App\Identity\Domain\Exception\OnboardingNoModalitySelectedException;
use App\Identity\Domain\Exception\UserAlreadyActiveException;
use App\Identity\Domain\Exception\UserAlreadySuspendedException;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\OnboardingStatus;
use App\Identity\Domain\ValueObject\UserLevel;
use App\Identity\Domain\ValueObject\UserProfile;
use App\Identity\Domain\ValueObject\UserRole;
use App\Identity\Domain\ValueObject\UserStatus;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;

/**
 * Cria um usuário de teste com dados padrão, evitando repetir o factory em cada teste.
 */
function makeUser(): User
{
    return User::register(
        cpf: Cpf::fromString('52998224725')->value(),
        email: Email::fromString('test@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'João Silva',
    );
}

it('creates a new user via register factory method', function () {
    $cpf = Cpf::fromString('52998224725')->value();
    $email = Email::fromString('test@example.com')->value();
    $password = HashedPassword::fromHash('hashed_password_123');

    $user = User::register(
        cpf: $cpf,
        email: $email,
        password: $password,
        name: 'João Silva',
    );

    expect($user->cpf()->value())->toBe($cpf->value())
        ->and($user->email()->value())->toBe($email->value())
        ->and($user->password()->value())->toBe($password->value())
        ->and($user->name())->toBe('João Silva')
        ->and($user->role())->toBe(UserRole::USER)
        ->and($user->level()->value())->toBe(UserLevel::initial()->value())
        ->and($user->onboardingStatus())->toBe(OnboardingStatus::PENDING)
        ->and($user->status())->toBe(UserStatus::ACTIVE)
        ->and($user->isActive())->toBeTrue()
        ->and($user->isSuspended())->toBeFalse();
});

it('completes onboarding and advances to level 2', function () {
    $user = makeUser();

    $profile = UserProfile::create(
        weight: 75.5,
        height: 1.78,
        modalities: ['caminhada', 'corrida'],
        primaryModality: 'caminhada',
    );

    $user->completeOnboarding(
        profile: $profile,
        modalities: ['caminhada', 'corrida'],
        primaryModality: 'caminhada',
    );

    $level = $user->level();
    $expectedLevel = UserLevel::fromInt(2)->value();

    expect($user->onboardingStatus())->toBe(OnboardingStatus::COMPLETED)
        ->and($level->value())->toBe($expectedLevel->value())
        ->and($user->profile()->weight())->toBe($profile->weight())
        ->and($user->profile()->height())->toBe($profile->height());
});

it('throws exception when completing onboarding without modalities', function () {
    $user = makeUser();

    $user->completeOnboarding(
        profile: UserProfile::create(),
        modalities: [],
        primaryModality: null,
    );
})->throws(OnboardingNoModalitySelectedException::class);

it('throws exception when completing onboarding twice', function () {
    $user = makeUser();

    $user->completeOnboarding(
        profile: UserProfile::create(modalities: ['caminhada']),
        modalities: ['caminhada'],
        primaryModality: null,
    );

    $user->completeOnboarding(
        profile: UserProfile::create(modalities: ['corrida']),
        modalities: ['corrida'],
        primaryModality: null,
    );
})->throws(OnboardingAlreadyCompletedException::class);

it('can create group after completing onboarding (level 2)', function () {
    $user = makeUser();

    expect($user->canCreateGroup())->toBeFalse();

    $user->completeOnboarding(
        profile: UserProfile::create(modalities: ['caminhada']),
        modalities: ['caminhada'],
        primaryModality: null,
    );

    expect($user->canCreateGroup())->toBeTrue();
});

it('suspends and reactivates user', function () {
    $user = makeUser();

    $user->suspend('Violation of terms');

    expect($user->isSuspended())->toBeTrue()
        ->and($user->isActive())->toBeFalse()
        ->and($user->canCreateGroup())->toBeFalse();

    $user->reactivate();

    expect($user->isActive())->toBeTrue()
        ->and($user->isSuspended())->toBeFalse();
});

it('throws exception when suspending already suspended user', function () {
    $user = makeUser();

    $user->suspend('Violation of terms');
    $user->suspend('Another violation');
})->throws(UserAlreadySuspendedException::class);

it('throws exception when reactivating already active user', function () {
    $user = makeUser();

    $user->reactivate();
})->throws(UserAlreadyActiveException::class);
