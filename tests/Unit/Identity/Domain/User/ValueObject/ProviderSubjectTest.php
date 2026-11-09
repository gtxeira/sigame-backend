<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\ProviderSubject;

it('trims the provider subject', function () {
    $subject = ProviderSubject::fromString('  google-sub  ');

    expect($subject->value())->toBe('google-sub');
});

it('rejects an empty provider subject', function () {
    ProviderSubject::fromString('   ');
})->throws(LogicException::class);

it('rejects a provider subject longer than the maximum length', function () {
    ProviderSubject::fromString(str_repeat('a', 256));
})->throws(LogicException::class);
