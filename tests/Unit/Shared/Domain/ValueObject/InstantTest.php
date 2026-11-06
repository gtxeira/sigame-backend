<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Instant;

function instantTestFromUtc(string $datetime): Instant
{
    return Instant::fromDateTime(new DateTimeImmutable($datetime, new DateTimeZone('UTC')));
}

it('formats an instant in America/Sao_Paulo across the day boundary', function () {
    // 02:30 UTC do dia 07 ainda é 23:30 do dia 06 em Brasília (UTC-3).
    $instant = instantTestFromUtc('2026-11-07T02:30:00+00:00');

    expect($instant->formatInTimezone(new DateTimeZone('America/Sao_Paulo'), 'd/m/Y H:i'))
        ->toBe('06/11/2026 23:30');
});

it('formats the exact boundary where the brazilian day starts', function () {
    $instant = instantTestFromUtc('2026-11-07T03:00:00+00:00');

    expect($instant->formatInTimezone(new DateTimeZone('America/Sao_Paulo'), 'd/m/Y H:i'))
        ->toBe('07/11/2026 00:00');
});

it('keeps a fixed -03:00 offset in january and july', function (string $utc, string $expected) {
    $instant = instantTestFromUtc($utc);

    expect($instant->formatInTimezone(new DateTimeZone('America/Sao_Paulo'), 'd/m/Y H:i'))
        ->toBe($expected);
})->with([
    'january' => ['2026-01-15T12:00:00+00:00', '15/01/2026 09:00'],
    'july' => ['2026-07-15T12:00:00+00:00', '15/07/2026 09:00'],
]);

it('does not mutate the instant timezone when converting', function () {
    $instant = instantTestFromUtc('2026-11-07T02:30:00+00:00');

    $converted = $instant->inTimezone(new DateTimeZone('America/Sao_Paulo'));

    expect($converted->getTimezone()->getName())->toBe('America/Sao_Paulo')
        ->and($instant->value()->getTimezone()->getName())->toBe('UTC')
        ->and($instant->formatInTimezone(new DateTimeZone('UTC'), 'd/m/Y H:i'))->toBe('07/11/2026 02:30');
});

it('respects a custom format on formatInTimezone', function () {
    $instant = instantTestFromUtc('2026-11-07T02:30:00+00:00');

    expect($instant->formatInTimezone(new DateTimeZone('America/Sao_Paulo')))
        ->toBe('2026-11-06T23:30:00.000-03:00');
});
