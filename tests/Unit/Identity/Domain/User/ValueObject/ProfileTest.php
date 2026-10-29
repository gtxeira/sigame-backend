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
        ->and($profile->photoUrl())->toBeNull();
});

it('stores physical data without changing the name', function () {
    $height = Height::fromCentimeters(178)->value();
    $weight = Weight::fromKilograms(75.5)->value();

    $profile = Profile::create('João Silva')
        ->withPhysicalData($height, $weight, 'https://example.com/photo.jpg');

    expect($profile->name())->toBe('João Silva')
        ->and($profile->height())->toBe($height)
        ->and($profile->weight())->toBe($weight)
        ->and($profile->photoUrl())->toBe('https://example.com/photo.jpg');
});

it('keeps the original profile untouched when physical data is added', function () {
    $original = Profile::create('João Silva');

    $updated = $original->withPhysicalData(
        Height::fromCentimeters(178)->value(),
        Weight::fromKilograms(75.5)->value(),
        null,
    );

    expect($original->height())->toBeNull()
        ->and($original->weight())->toBeNull()
        ->and($updated)->not->toBe($original);
});

it('clears physical data when onboarding is repeated without it', function () {
    $height = Height::fromCentimeters(178)->value();

    $profile = Profile::create('João Silva')
        ->withPhysicalData($height, Weight::fromKilograms(75.5)->value(), 'https://example.com/photo.jpg')
        ->withPhysicalData(null, null, null);

    expect($profile->height())->toBeNull()
        ->and($profile->weight())->toBeNull()
        ->and($profile->photoUrl())->toBeNull();
});
