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

/**
 * @return list<string>
 */
function domainExceptionErrorCodes(): array
{
    $catalogs = [SharedErrorCode::class, IdentityErrorCode::class];

    $codes = [];

    foreach ($catalogs as $catalog) {
        foreach ($catalog::cases() as $case) {
            $codes[] = $case->value;
        }
    }

    return $codes;
}

it('exposes a cataloged code and a public message', function (string $exceptionClass) {
    /** @var class-string<DomainException> $exceptionClass */
    $exception = new $exceptionClass();

    expect(domainExceptionErrorCodes())->toContain($exception->errorCode())
        ->and($exception->publicMessage())->not->toBeEmpty()
        ->and($exception->publicMessage())->not->toBe($exception->errorCode());
})->with([
    InvalidUuidException::class,
    InvalidHashedException::class,
    OnboardingAlreadyCompletedException::class,
    OnboardingInvalidPrimaryModalityException::class,
    OnboardingNoModalitySelectedException::class,
    UserAlreadyActiveException::class,
    UserAlreadySuspendedException::class,
]);
