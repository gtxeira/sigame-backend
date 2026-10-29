<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\Credentials;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;

it('creates credentials with email and hashed password', function () {
    $email = Email::fromString('user@example.com')->value();
    $password = HashedPassword::fromHash('hashed_password_123');

    $credentials = Credentials::create($email, $password);

    expect($credentials->email())->toBe($email)
        ->and($credentials->password())->toBe($password);
});

it('keeps the normalized email from the value object', function () {
    $email = Email::fromString('  User@Example.COM ')->value();

    $credentials = Credentials::create($email, HashedPassword::fromHash('hashed_password_123'));

    expect($credentials->email()->value())->toBe('user@example.com');
});
