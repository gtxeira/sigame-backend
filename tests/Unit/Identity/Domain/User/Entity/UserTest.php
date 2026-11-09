<?php

declare(strict_types=1);

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Exception\UserCpfAlreadySetException;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\OnboardingStatus;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Identity\Domain\User\ValueObject\UserLevel;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Domain\User\ValueObject\UserStatus;
use App\Identity\Domain\User\ValueObject\Weight;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Modality;

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

    expect($user->cpf()?->value())->toBe($cpf->value())
        ->and($user->email()->value())->toBe($email->value())
        ->and($user->password()?->value())->toBe($password->value())
        ->and($user->name())->toBe('João Silva')
        ->and($user->role())->toBe(UserRole::USER)
        ->and($user->level()->value())->toBe(UserLevel::initial()->value())
        ->and($user->onboardingStatus())->toBe(OnboardingStatus::PENDING)
        ->and($user->status())->toBe(UserStatus::ACTIVE)
        ->and($user->height())->toBeNull()
        ->and($user->weight())->toBeNull()
        ->and($user->photoKey())->toBeNull()
        ->and($user->modalities())->toBe([])
        ->and($user->primaryModality())->toBeNull()
        ->and($user->isActive())->toBeTrue()
        ->and($user->isSuspended())->toBeFalse();
});

it('initializes updatedAt with the same instant as createdAt', function () {
    $user = makeUser();

    expect($user->updatedAt()->equals($user->createdAt()))->toBeTrue();
});

it('initializes updatedAt with the same instant as createdAt for provider registrations', function () {
    $user = User::registerWithProvider(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google-subject'),
        email: Email::fromString('provider@example.com')->value(),
    );

    expect($user->updatedAt()->equals($user->createdAt()))->toBeTrue();
});

it('advances updatedAt on mutations', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('updated-at@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'João Silva',
    );
    $createdUpdatedAt = $user->updatedAt();

    $user->assignCpf(Cpf::fromString('47705122014')->value());

    expect($user->updatedAt()->equals($createdUpdatedAt))->toBeFalse()
        ->and($user->updatedAt()->isBefore($user->createdAt()))->toBeFalse();
});

it('completes onboarding and advances to level 2', function () {
    $user = makeUser();

    $user->completeOnboarding(
        modalities: [Modality::WALKING, Modality::RUNNING],
        primaryModality: Modality::WALKING,
    );

    expect($user->onboardingStatus())->toBe(OnboardingStatus::COMPLETED)
        ->and($user->level()->value())->toBe(2)
        ->and($user->height())->toBeNull()
        ->and($user->weight())->toBeNull()
        ->and($user->photoKey())->toBeNull()
        ->and($user->modalities())->toBe([Modality::WALKING, Modality::RUNNING])
        ->and($user->primaryModality())->toBe(Modality::WALKING)
        ->and($user->hasModality(Modality::RUNNING))->toBeTrue()
        ->and($user->hasModality(Modality::CYCLING))->toBeFalse();
});

it('completes onboarding without optional profile data', function () {
    $user = makeUser();

    $user->completeOnboarding(
        modalities: [Modality::CYCLING],
        primaryModality: null,
    );

    expect($user->onboardingStatus())->toBe(OnboardingStatus::COMPLETED)
        ->and($user->height())->toBeNull()
        ->and($user->weight())->toBeNull()
        ->and($user->photoKey())->toBeNull()
        ->and($user->modalities())->toBe([Modality::CYCLING])
        ->and($user->primaryModality())->toBeNull();
});

it('deduplicates modalities and keeps a deterministic order', function () {
    $user = makeUser();

    $user->completeOnboarding(
        modalities: [Modality::RUNNING, Modality::WALKING, Modality::RUNNING],
        primaryModality: null,
    );

    expect($user->modalities())->toBe([Modality::WALKING, Modality::RUNNING]);
});

it('rejects completing onboarding without modalities', function () {
    $user = makeUser();

    $result = $user->completeOnboarding(
        modalities: [],
        primaryModality: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe(IdentityErrorCode::OnboardingNoModalitySelected->value)
        ->and($user->onboardingStatus())->toBe(OnboardingStatus::PENDING)
        ->and($user->level()->value())->toBe(1)
        ->and($user->modalities())->toBe([]);
});

it('rejects a primary modality that is not selected', function () {
    $user = makeUser();

    $result = $user->completeOnboarding(
        modalities: [Modality::WALKING],
        primaryModality: Modality::RUNNING,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe(IdentityErrorCode::OnboardingInvalidPrimaryModality->value)
        ->and($user->onboardingStatus())->toBe(OnboardingStatus::PENDING)
        ->and($user->modalities())->toBe([]);
});

it('updates profile data while preserving the photo key', function () {
    $user = makeUser();
    $user->changePhoto('users/'.$user->id()->value().'/photo.png');

    $user->changeProfile(
        $user->profile()
            ->withName('Maria Silva')
            ->withHeight(Height::fromCentimeters(165)->value())
            ->withWeight(Weight::fromKilograms(62.5)->value()),
    );

    expect($user->name())->toBe('Maria Silva')
        ->and($user->height()?->centimeters())->toBe(165)
        ->and($user->weight()?->kilograms())->toBe(62.5)
        ->and($user->photoKey())->toBe('users/'.$user->id()->value().'/photo.png');
});

it('removes the photo key', function () {
    $user = makeUser();
    $user->changePhoto('users/photo.png');

    $user->changePhoto(null);

    expect($user->photoKey())->toBeNull();
});

it('rejects completing onboarding twice', function () {
    $user = makeUser();

    $user->completeOnboarding(
        modalities: [Modality::WALKING],
        primaryModality: null,
    );

    $result = $user->completeOnboarding(
        modalities: [Modality::RUNNING],
        primaryModality: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe(IdentityErrorCode::OnboardingAlreadyCompleted->value)
        ->and($user->onboardingStatus())->toBe(OnboardingStatus::COMPLETED)
        ->and($user->level()->value())->toBe(2)
        ->and($user->modalities())->toBe([Modality::WALKING]);
});

it('can create group after completing onboarding (level 2)', function () {
    $user = makeUser();

    expect($user->canCreateGroup())->toBeFalse();

    $user->completeOnboarding(
        modalities: [Modality::WALKING],
        primaryModality: null,
    );

    expect($user->canCreateGroup())->toBeTrue();
});

it('suspends and reactivates user', function () {
    $user = makeUser();

    expect($user->suspend('Violation of terms'))->toBeTrue();

    expect($user->isSuspended())->toBeTrue()
        ->and($user->isActive())->toBeFalse()
        ->and($user->canCreateGroup())->toBeFalse();

    expect($user->reactivate())->toBeTrue();

    expect($user->isActive())->toBeTrue()
        ->and($user->isSuspended())->toBeFalse();
});

it('keeps the suspended state when suspending twice', function () {
    $user = makeUser();
    $user->suspend('Violation of terms');
    $updatedAt = $user->updatedAt();

    expect($user->suspend('Another violation'))->toBeFalse()
        ->and($user->isSuspended())->toBeTrue()
        ->and($user->updatedAt()->equals($updatedAt))->toBeTrue();
});

it('keeps the active state when reactivating an active user', function () {
    $user = makeUser();
    $updatedAt = $user->updatedAt();

    expect($user->reactivate())->toBeFalse()
        ->and($user->isActive())->toBeTrue()
        ->and($user->updatedAt()->equals($updatedAt))->toBeTrue();
});

it('registers a user without cpf', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('no-cpf@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'João Silva',
    );

    expect($user->cpf())->toBeNull()
        ->and($user->email()->value())->toBe('no-cpf@example.com');
});

it('assigns the cpf once', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('assign-cpf@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'João Silva',
    );
    $newCpf = Cpf::fromString('47705122014')->value();

    $user->assignCpf($newCpf);

    expect($user->cpf()?->value())->toBe('47705122014');

    $user->assignCpf($newCpf);

    expect($user->cpf()?->value())->toBe('47705122014');
});

it('rejects changing an already defined cpf', function () {
    $user = makeUser();

    $user->assignCpf(Cpf::fromString('47705122014')->value());
})->throws(UserCpfAlreadySetException::class);
