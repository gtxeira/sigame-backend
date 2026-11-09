<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Domain\User\ValueObject\Profile;
use App\Identity\Domain\User\ValueObject\Weight;

it('creates a profile with name and empty physical data', function () {
    $profile = Profile::create('João Silva');

    expect($profile->name())->toBe('João Silva')
        ->and($profile->height())->toBeNull()
        ->and($profile->weight())->toBeNull()
        ->and($profile->photoKey())->toBeNull();
});

it('stores physical data without changing the name', function () {
    $height = Height::fromCentimeters(178)->value();
    $weight = Weight::fromKilograms(75.5)->value();

    $profile = Profile::create('João Silva')
        ->withHeight($height)
        ->withWeight($weight)
        ->withPhoto('users/photo.png');

    expect($profile->name())->toBe('João Silva')
        ->and($profile->height())->toBe($height)
        ->and($profile->weight())->toBe($weight)
        ->and($profile->photoKey())->toBe('users/photo.png');
});

it('keeps the original profile untouched when a field changes', function () {
    $original = Profile::create('João Silva');

    $updated = $original
        ->withHeight(Height::fromCentimeters(178)->value())
        ->withWeight(Weight::fromKilograms(75.5)->value());

    expect($original->height())->toBeNull()
        ->and($original->weight())->toBeNull()
        ->and($updated)->not->toBe($original);
});

it('clears physical data without clearing the photo', function () {
    $profile = Profile::create('João Silva')
        ->withHeight(Height::fromCentimeters(178)->value())
        ->withWeight(Weight::fromKilograms(75.5)->value())
        ->withPhoto('users/photo.png')
        ->withHeight(null)
        ->withWeight(null);

    expect($profile->height())->toBeNull()
        ->and($profile->weight())->toBeNull()
        ->and($profile->photoKey())->toBe('users/photo.png');
});

it('replaces the name preserving the remaining fields', function () {
    $profile = Profile::create('João Silva')
        ->withHeight(Height::fromCentimeters(178)->value())
        ->withPhoto('users/photo.png')
        ->withName('Maria Silva');

    expect($profile->name())->toBe('Maria Silva')
        ->and($profile->height()?->centimeters())->toBe(178)
        ->and($profile->photoKey())->toBe('users/photo.png');
});
