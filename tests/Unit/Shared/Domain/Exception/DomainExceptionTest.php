<?php

declare(strict_types=1);

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Exception\OnboardingAlreadyCompletedException;
use App\Identity\Domain\Exception\OnboardingInvalidPrimaryModalityException;
use App\Identity\Domain\Exception\OnboardingNoModalitySelectedException;
use App\Identity\Domain\Exception\UserAlreadyActiveException;
use App\Identity\Domain\Exception\UserAlreadySuspendedException;
use App\Shared\Domain\Error\SharedErrorCode;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\InvalidHashedException;
use App\Shared\Domain\Exception\InvalidUuidException;

it('exposes a cataloged code, kind and public message', function (
    string $exceptionClass,
    SharedErrorCode|IdentityErrorCode $code,
) {
    /** @var class-string<DomainException> $exceptionClass */
    $exception = new $exceptionClass();

    expect($exception->errorCode())->toBe($code->value)
        ->and($exception->kind())->toBe($code->kind())
        ->and($exception->publicMessage())->not->toBeEmpty()
        ->and($exception->publicMessage())->not->toBe($exception->errorCode());
})->with([
    [InvalidUuidException::class, SharedErrorCode::InvalidUuid],
    [InvalidHashedException::class, SharedErrorCode::InvalidHashedPassword],
    [OnboardingAlreadyCompletedException::class, IdentityErrorCode::OnboardingAlreadyCompleted],
    [OnboardingInvalidPrimaryModalityException::class, IdentityErrorCode::OnboardingInvalidPrimaryModality],
    [OnboardingNoModalitySelectedException::class, IdentityErrorCode::OnboardingNoModalitySelected],
    [UserAlreadyActiveException::class, IdentityErrorCode::UserAlreadyActive],
    [UserAlreadySuspendedException::class, IdentityErrorCode::UserAlreadySuspended],
]);
