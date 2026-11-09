<?php

declare(strict_types=1);

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Exception\IdentityException;
use App\Identity\Domain\User\Exception\InvalidHashedPasswordException;
use App\Identity\Domain\User\Exception\UserCpfAlreadySetException;

it('exposes a cataloged code, kind and public message', function (
    string $exceptionClass,
    IdentityErrorCode $code,
) {
    /** @var class-string<IdentityException> $exceptionClass */
    $exception = new $exceptionClass();

    expect($exception->errorCode())->toBe($code->value)
        ->and($exception->kind())->toBe($code->kind())
        ->and($exception->publicMessage())->not->toBeEmpty()
        ->and($exception->publicMessage())->not->toBe($exception->errorCode());
})->with([
    [InvalidHashedPasswordException::class, IdentityErrorCode::InvalidHashedPassword],
    [UserCpfAlreadySetException::class, IdentityErrorCode::UserCpfAlreadySet],
]);
