<?php

declare(strict_types=1);

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Exception\IdentityException;
use App\Identity\Domain\User\Exception\CpfAlreadyExistsException;
use App\Identity\Domain\User\Exception\EmailAlreadyExistsException;
use App\Identity\Domain\User\Exception\SocialIdentityAlreadyLinkedException;
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
    [CpfAlreadyExistsException::class, IdentityErrorCode::UserCpfAlreadyExists],
    [EmailAlreadyExistsException::class, IdentityErrorCode::EmailAlreadyExists],
    [SocialIdentityAlreadyLinkedException::class, IdentityErrorCode::SocialIdentityAlreadyLinked],
    [UserCpfAlreadySetException::class, IdentityErrorCode::UserCpfAlreadySet],
]);
