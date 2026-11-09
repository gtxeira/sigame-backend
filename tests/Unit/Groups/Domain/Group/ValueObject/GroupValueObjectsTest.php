<?php

declare(strict_types=1);

use App\Groups\Domain\Group\ValueObject\CommunicationLink;
use App\Groups\Domain\Group\ValueObject\GroupDescription;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\RemovalReason;

it('normalizes a valid group name', function () {
    $name = GroupName::fromString('  Corrida no Parque  ')->value();

    expect($name->value())->toBe('Corrida no Parque');
});

it('rejects invalid group names', function (string $input) {
    expect(GroupName::fromString($input)->isInvalid())->toBeTrue();
})->with([
    'empty' => '   ',
    'too short' => 'ab',
    'too long' => str_repeat('a', 81),
]);

it('accepts http and https communication links', function (string $input) {
    expect(CommunicationLink::fromString($input)->isValid())->toBeTrue();
})->with([
    'https' => 'https://chat.example.com/grupo',
    'http' => 'http://chat.example.com/grupo',
]);

it('rejects invalid communication links', function (string $input) {
    expect(CommunicationLink::fromString($input)->isInvalid())->toBeTrue();
})->with([
    'empty' => '',
    'not a url' => 'chat do grupo',
    'unsupported scheme' => 'ftp://chat.example.com/grupo',
    'too long' => 'https://example.com/'.str_repeat('a', 2048),
]);

it('validates description and removal reason limits', function () {
    expect(GroupDescription::fromString(str_repeat('a', 501))->isInvalid())->toBeTrue()
        ->and(GroupDescription::fromString('Descrição válida')->isValid())->toBeTrue()
        ->and(RemovalReason::fromString('   ')->isInvalid())->toBeTrue()
        ->and(RemovalReason::fromString(str_repeat('a', 501))->isInvalid())->toBeTrue()
        ->and(RemovalReason::fromString('Motivo')->isValid())->toBeTrue();
});
