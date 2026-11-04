<?php

declare(strict_types=1);

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Entity\UserIdentity;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;

function identityUser(): User
{
    return User::register(
        cpf: null,
        email: Email::fromString('identity@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Identity User',
    );
}

it('creates a local identity from email and password', function () {
    $user = identityUser();
    $identity = $user->localIdentity();

    assert($identity instanceof UserIdentity);

    expect($identity->provider())->toBe(IdentityProvider::LOCAL)
        ->and($identity->providerSubject()->value())->toBe('identity@example.com')
        ->and($identity->email()?->value())->toBe('identity@example.com')
        ->and($identity->password()?->value())->toBe('hashed')
        ->and($identity->emailVerifiedAt())->toBeNull()
        ->and($identity->createdAt()->timestamp())->toBeLessThanOrEqual(time());
});

it('creates a social identity without password', function () {
    $user = identityUser();

    $user->linkSocialIdentity(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google-sub'),
        email: Email::fromString('social@example.com')->value(),
    );

    $identity = $user->identities()[1];

    expect($identity->provider())->toBe(IdentityProvider::GOOGLE)
        ->and($identity->providerSubject()->value())->toBe('google-sub')
        ->and($identity->password())->toBeNull()
        ->and($identity->emailVerifiedAt())->not->toBeNull();
});

it('does not duplicate the same social identity', function () {
    $user = identityUser();
    $subject = ProviderSubject::fromString('google-sub');
    $email = Email::fromString('social@example.com')->value();

    $user->linkSocialIdentity(IdentityProvider::GOOGLE, $subject, $email);
    $user->linkSocialIdentity(IdentityProvider::GOOGLE, $subject, $email);

    expect($user->identities())->toHaveCount(2);
});
