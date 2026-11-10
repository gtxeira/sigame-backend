<?php

declare(strict_types=1);

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Exception\CpfAlreadyExistsException;
use App\Identity\Domain\User\Exception\SocialIdentityAlreadyLinkedException;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSearchCriteria;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Domain\User\ValueObject\UserStatus;
use App\Identity\Domain\User\ValueObject\Weight;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Modality;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

function makeUserWith(string $cpf, string $email, string $plainPassword, string $name): User
{
    return User::register(
        cpf: Cpf::fromString($cpf)->value(),
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash($plainPassword),
        name: $name,
    );
}

it('persists and reloads profile data and modalities', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);

    $user = makeUserWith(
        cpf: '52998224725',
        email: 'persist@example.com',
        plainPassword: 'hashed_password_123',
        name: 'Maria Silva',
    );

    $user->completeOnboarding(
        modalities: [Modality::RUNNING, Modality::WALKING],
        primaryModality: Modality::RUNNING,
    );
    $user->changePhoto('users/maria/photo.png');
    $user->changeProfile(
        $user->profile()
            ->withHeight(Height::fromCentimeters(165)->value())
            ->withWeight(Weight::fromKilograms(62.5)->value()),
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
        ->and($found->photoKey())->toBe('users/maria/photo.png')
        ->and($found->name())->toBe('Maria Silva')
        ->and($found->email()->value())->toBe('persist@example.com')
        ->and($found->password()?->value())->toBe('hashed_password_123')
        ->and($found->role())->toBe(UserRole::USER)
        ->and($found->status())->toBe(UserStatus::ACTIVE)
        ->and($found->modalities())->toBe([Modality::WALKING, Modality::RUNNING])
        ->and($found->primaryModality())->toBe(Modality::RUNNING)
        ->and($found->hasModality(Modality::WALKING))->toBeTrue()
        ->and($found->hasModality(Modality::CYCLING))->toBeFalse();
});

it('reloads a user without onboarding data', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);

    $user = makeUserWith(
        cpf: '52998224725',
        email: 'pending@example.com',
        plainPassword: 'hashed_password_123',
        name: 'João Silva',
    );

    $repository->save($user);
    $em->clear();

    $found = $repository->findById($user->id());

    assert($found instanceof User);

    expect($found->height())->toBeNull()
        ->and($found->weight())->toBeNull()
        ->and($found->photoKey())->toBeNull()
        ->and($found->modalities())->toBe([])
        ->and($found->primaryModality())->toBeNull();
});

it('allows only one primary modality per user', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);

    $user = makeUserWith(
        cpf: '52998224725',
        email: 'primary@example.com',
        plainPassword: 'hashed_password_123',
        name: 'Ana Silva',
    );

    $user->completeOnboarding(
        modalities: [Modality::WALKING],
        primaryModality: Modality::WALKING,
    );

    $repository->save($user);

    $em->getConnection()->executeStatement(
        'INSERT INTO user_modalities (user_id, modality, is_primary) VALUES (?, ?, TRUE)',
        [$user->id()->value(), Modality::CYCLING->value],
    );
})->throws(UniqueConstraintViolationException::class);

it('persists updatedAt equal to createdAt and advances it after changes', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);

    $user = makeUserWith(
        cpf: '52998224725',
        email: 'updated-at@example.com',
        plainPassword: 'hashed_password_123',
        name: 'Maria Silva',
    );
    $createdAt = $user->createdAt();

    $repository->save($user);
    $em->clear();

    $found = $repository->findById($user->id());

    assert($found instanceof User);

    expect($found->createdAt()->equals($createdAt))->toBeTrue()
        ->and($found->updatedAt()->equals($found->createdAt()))->toBeTrue();

    $found->completeOnboarding(
        modalities: [Modality::WALKING],
        primaryModality: null,
    );

    $repository->save($found);
    $em->clear();

    $reloaded = $repository->findById($user->id());

    assert($reloaded instanceof User);

    expect($reloaded->updatedAt()->equals($reloaded->createdAt()))->toBeFalse()
        ->and($reloaded->updatedAt()->isBefore($reloaded->createdAt()))->toBeFalse();
});

it('finds a user by email through the local identity', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);

    $user = makeUserWith(
        cpf: '52998224725',
        email: 'lookup@example.com',
        plainPassword: 'hashed_password_123',
        name: 'Ana Silva',
    );

    $repository->save($user);
    $em->clear();

    $found = $repository->findByEmail(Email::fromString('lookup@example.com')->value());

    assert($found instanceof User);

    expect($found->id()->value())->toBe($user->id()->value());
});

it('finds a user by provider subject', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);

    $user = makeUserWith(
        cpf: '52998224725',
        email: 'provider@example.com',
        plainPassword: 'hashed_password_123',
        name: 'Provider User',
    );

    $user->linkSocialIdentity(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google-subject'),
        email: Email::fromString('provider@example.com')->value(),
    );

    $repository->save($user);
    $em->clear();

    $found = $repository->findByProviderSubject(
        IdentityProvider::GOOGLE,
        ProviderSubject::fromString('google-subject'),
    );

    assert($found instanceof User);

    expect($found->id()->value())->toBe($user->id()->value());
});

it('translates a duplicate cpf into a domain conflict', function () {
    $repository = $this->service(UserRepositoryInterface::class);

    $first = makeUserWith(
        cpf: '52998224725',
        email: 'cpf-first@example.com',
        plainPassword: 'hashed_password_123',
        name: 'First User',
    );
    $repository->save($first);

    $second = makeUserWith(
        cpf: '52998224725',
        email: 'cpf-second@example.com',
        plainPassword: 'hashed_password_123',
        name: 'Second User',
    );

    expect(fn () => $repository->save($second))->toThrow(CpfAlreadyExistsException::class);
});

it('translates a duplicate social identity into a domain conflict', function () {
    $repository = $this->service(UserRepositoryInterface::class);
    $subject = ProviderSubject::fromString('same-google-subject');

    $first = makeUserWith(
        cpf: '52998224725',
        email: 'social-first@example.com',
        plainPassword: 'hashed_password_123',
        name: 'First User',
    );
    $first->linkSocialIdentity(
        IdentityProvider::GOOGLE,
        $subject,
        Email::fromString('social-first@example.com')->value(),
    );
    $repository->save($first);

    $second = makeUserWith(
        cpf: '47705122014',
        email: 'social-second@example.com',
        plainPassword: 'hashed_password_123',
        name: 'Second User',
    );
    $second->linkSocialIdentity(
        IdentityProvider::GOOGLE,
        $subject,
        Email::fromString('social-second@example.com')->value(),
    );

    expect(fn () => $repository->save($second))->toThrow(SocialIdentityAlreadyLinkedException::class);
});

it('searches accounts and counts active admins', function () {
    $repository = $this->service(UserRepositoryInterface::class);

    $admin = makeUserWith(
        cpf: '52998224725',
        email: 'admin-count@example.com',
        plainPassword: 'hashed_password_123',
        name: 'Ana Admin',
    );
    $admin->grantAdminRole();
    $repository->save($admin);

    $user = makeUserWith(
        cpf: '47705122014',
        email: 'user-count@example.com',
        plainPassword: 'hashed_password_123',
        name: 'Bruno User',
    );
    $repository->save($user);

    expect($repository->countActiveAdmins())->toBe(1);

    $admins = $repository->search(new UserSearchCriteria(role: UserRole::ADMIN_SIGAME));
    expect($admins->total)->toBe(1)
        ->and($admins->items[0]->id()->value())->toBe($admin->id()->value());

    $search = $repository->search(new UserSearchCriteria(query: 'bruno'));
    expect($search->total)->toBe(1)
        ->and($search->items[0]->id()->value())->toBe($user->id()->value());

    $suspended = $repository->search(new UserSearchCriteria(status: UserStatus::SUSPENDED));
    expect($suspended->total)->toBe(0);
});
