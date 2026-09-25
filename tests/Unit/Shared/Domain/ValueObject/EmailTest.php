<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Email;

it('creates a valid email from string', function () {
    $result = Email::fromString('user@example.com');

    expect($result->isValid())->toBeTrue()
        ->and($result->value()->value())->toBe('user@example.com');
});

it('normalizes email to lowercase', function () {
    $result = Email::fromString('USER@EXAMPLE.COM');

    expect($result->isValid())->toBeTrue()
        ->and($result->value()->value())->toBe('user@example.com');
});

it('trims whitespace from email', function () {
    $result = Email::fromString('  user@example.com  ');

    expect($result->isValid())->toBeTrue()
        ->and($result->value()->value())->toBe('user@example.com');
});

it('rejects invalid email format', function () {
    $result = Email::fromString('not-an-email');

    expect($result->isInvalid())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_INVALID')
        ->and($result->errors()[0]->field)->toBe('email');
});

it('rejects email without domain', function () {
    $result = Email::fromString('user@');

    expect($result->isInvalid())->toBeTrue();
});

it('rejects email without local part', function () {
    $result = Email::fromString('@example.com');

    expect($result->isInvalid())->toBeTrue();
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
