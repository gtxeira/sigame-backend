<?php

declare(strict_types=1);

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Error\SharedErrorCode;
use App\Shared\Presentation\Http\Response\ApiErrorCode;

/**
 * @return list<string>
 */
function allErrorCodes(): array
{
    $catalogs = [
        SharedErrorCode::class,
        IdentityErrorCode::class,
        ApiErrorCode::class,
    ];

    $codes = [];

    foreach ($catalogs as $catalog) {
        foreach ($catalog::cases() as $case) {
            $codes[] = $case->value;
        }
    }

    return $codes;
}

it('keeps error codes unique across catalogs', function () {
    $codes = allErrorCodes();

    expect($codes)->toBe(array_values(array_unique($codes)));
});

it('uses screaming snake case for every error code', function (string $code) {
    expect($code)->toMatch('/^[A-Z][A-Z0-9_]*$/');
})->with(fn (): array => allErrorCodes());
