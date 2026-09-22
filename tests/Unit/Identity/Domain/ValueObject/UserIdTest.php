<?php

declare(strict_types=1);

use App\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Exception\InvalidUuidException;

it('generates a unique user id', function () {
    $id1 = UserId::generate();
    $id2 = UserId::generate();

    expect($id1->value())->not->toBe($id2->value());
});

it('creates user id from string', function () {
    $uuid = '01932c08-7b5e-7d12-8a4f-123456789abc';
    $id = UserId::fromString($uuid);

    expect($id->value())->toBe($uuid);
});

it('throws exception for invalid uuid', function () {
    UserId::fromString('invalid-uuid');
})->throws(InvalidUuidException::class);

it('compares user ids by value', function () {
    $uuid = '01932c08-7b5e-7d12-8a4f-123456789abc';
    $id1 = UserId::fromString($uuid);
    $id2 = UserId::fromString($uuid);

    expect($id1->equals($id2))->toBeTrue();
});

it('distinguishes different user ids', function () {
    $id1 = UserId::generate();
    $id2 = UserId::generate();

    expect($id1->equals($id2))->toBeFalse();
});
