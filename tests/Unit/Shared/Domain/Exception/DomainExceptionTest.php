<?php

declare(strict_types=1);

use App\Shared\Domain\Error\SharedErrorCode;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\InvalidUuidException;

it('exposes a cataloged code, kind and public message', function (
    string $exceptionClass,
    SharedErrorCode $code,
) {
    /** @var class-string<DomainException> $exceptionClass */
    $exception = new $exceptionClass();

    expect($exception->errorCode())->toBe($code->value)
        ->and($exception->kind())->toBe($code->kind())
        ->and($exception->publicMessage())->not->toBeEmpty()
        ->and($exception->publicMessage())->not->toBe($exception->errorCode());
})->with([
    [InvalidUuidException::class, SharedErrorCode::InvalidUuid],
]);
