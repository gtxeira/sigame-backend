<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Email;

it('creates a valid email from string', function () {
    $result = Email::fromString('user@example.com');

    expect($result->isValid())->toBeTrue();

    $email = $result->value();

    expect($email->value())->toBe('user@example.com');
});

it('normalizes email', function (string $input, string $expected) {
    $result = Email::fromString($input);

    expect($result->isValid())->toBeTrue();

    $email = $result->value();

    expect($email->value())->toBe($expected);
})->with([
    'lowercase' => ['user@example.com', 'user@example.com'],
    'uppercase' => ['USER@EXAMPLE.COM', 'user@example.com'],
    'with whitespace' => ['  user@example.com  ', 'user@example.com'],
]);

it('rejects invalid email', function (string $input) {
    $result = Email::fromString($input);

    expect($result->isInvalid())->toBeTrue();
})->with([
    'invalid format' => 'not-an-email',
    'without domain' => 'user@',
    'without local part' => '@example.com',
]);

it('reports invalid email with code and field', function () {
    $result = Email::fromString('not-an-email');

    expect($result->isInvalid())->toBeTrue();

    $error = $result->errors()[0];

    expect($error->code)->toBe('EMAIL_INVALID')
        ->and($error->field)->toBe('email');
});

it('extracts domain from email', function () {
    $email = Email::fromString('user@example.com')->value();

    expect($email->domain())->toBe('example.com');
});

it('compares emails by value', function () {
    $email1 = Email::fromString('user@example.com')->value();
    $email2 = Email::fromString('user@example.com')->value();

    expect($email1->equals($email2))->toBeTrue();
});

it('distinguishes different emails', function () {
    $email1 = Email::fromString('user1@example.com')->value();
    $email2 = Email::fromString('user2@example.com')->value();

    expect($email1->equals($email2))->toBeFalse();
});

it('converts to string', function () {
    $email = Email::fromString('user@example.com')->value();

    expect((string) $email)->toBe('user@example.com');
});
