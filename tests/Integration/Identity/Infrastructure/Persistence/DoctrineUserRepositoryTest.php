<?php

declare(strict_types=1);

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Modality;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

it('persists and reloads profile data and modalities', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);

    $user = User::register(
        cpf: Cpf::fromString('52998224725')->value(),
        email: Email::fromString('persist@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Maria Silva',
    );

    $user->completeOnboarding(
        heightCm: 165,
        weightKg: 62.5,
        photoUrl: 'https://example.com/maria.jpg',
        modalities: [Modality::RUNNING, Modality::WALKING],
        primaryModality: Modality::RUNNING,
    );

    $repository->save($user);
    $em->clear();

    $found = $repository->findById($user->id());

    assert($found instanceof User);

    $height = $found->height();
    $weight = $found->weight();

    expect($height?->centimeters())->toBe(165)
        ->and($height?->meters())->toBe(1.65)
        ->and($weight?->kilograms())->toBe(62.5)
        ->and($found->photoUrl())->toBe('https://example.com/maria.jpg')
        ->and($found->modalities())->toBe([Modality::WALKING, Modality::RUNNING])
        ->and($found->primaryModality())->toBe(Modality::RUNNING)
        ->and($found->hasModality(Modality::WALKING))->toBeTrue()
        ->and($found->hasModality(Modality::CYCLING))->toBeFalse();
});

it('reloads a user without onboarding data', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);

    $user = User::register(
        cpf: Cpf::fromString('52998224725')->value(),
        email: Email::fromString('pending@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'João Silva',
    );

    $repository->save($user);
    $em->clear();

    $found = $repository->findById($user->id());

    assert($found instanceof User);

    expect($found->height())->toBeNull()
        ->and($found->weight())->toBeNull()
        ->and($found->photoUrl())->toBeNull()
        ->and($found->modalities())->toBe([])
        ->and($found->primaryModality())->toBeNull();
});

it('allows only one primary modality per user', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);

    $user = User::register(
        cpf: Cpf::fromString('52998224725')->value(),
        email: Email::fromString('primary@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Ana Silva',
    );

    $user->completeOnboarding(
        heightCm: null,
        weightKg: null,
        photoUrl: null,
        modalities: [Modality::WALKING],
        primaryModality: Modality::WALKING,
    );

    $repository->save($user);

    $em->getConnection()->executeStatement(
        'INSERT INTO user_modalities (user_id, modality, is_primary) VALUES (?, ?, TRUE)',
        [$user->id()->value(), Modality::CYCLING->value],
    );
})->throws(UniqueConstraintViolationException::class);
