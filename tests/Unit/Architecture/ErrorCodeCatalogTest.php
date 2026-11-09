<?php

declare(strict_types=1);

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Error\ErrorKind;
use App\Shared\Domain\Error\SharedErrorCode;
use App\Shared\Presentation\Http\Response\ApiErrorCode;

/**
 * @return list<string>
 */
function allErrorCodes(): array
{
    $codes = [];

    foreach (errorCodeCatalogs() as $catalog) {
        foreach ($catalog::cases() as $case) {
            $codes[] = $case->value;
        }
    }

    return $codes;
}

/**
 * @return list<class-string<SharedErrorCode|IdentityErrorCode|ApiErrorCode>>
 */
function errorCodeCatalogs(): array
{
    return [
        SharedErrorCode::class,
        IdentityErrorCode::class,
        ApiErrorCode::class,
    ];
}

it('keeps error codes unique across catalogs', function () {
    $codes = allErrorCodes();

    expect($codes)->toBe(array_values(array_unique($codes)));
});

it('uses screaming snake case for every error code', function (string $code) {
    expect($code)->toMatch('/^[A-Z][A-Z0-9_]*$/');
})->with(fn (): array => allErrorCodes());

it('classifies every domain error code', function (SharedErrorCode|IdentityErrorCode $code) {
    expect(ErrorKind::cases())->toContain($code->kind());
})->with(fn (): array => [
    ...SharedErrorCode::cases(),
    ...IdentityErrorCode::cases(),
]);

it('classifies state conflicts as conflict', function (IdentityErrorCode $code) {
    expect($code->kind())->toBe(ErrorKind::Conflict);
})->with([
    IdentityErrorCode::UserCpfAlreadyExists,
    IdentityErrorCode::EmailAlreadyExists,
    IdentityErrorCode::OnboardingAlreadyCompleted,
    IdentityErrorCode::EmailAlreadyVerified,
    IdentityErrorCode::UserCpfAlreadySet,
    IdentityErrorCode::RefreshInProgress,
]);

it('classifies input problems as validation', function (SharedErrorCode|IdentityErrorCode $code) {
    expect($code->kind())->toBe(ErrorKind::Validation);
})->with([
    SharedErrorCode::CpfInvalid,
    SharedErrorCode::EmailInvalid,
    SharedErrorCode::DateTimeInvalid,
    SharedErrorCode::InvalidUuid,
    IdentityErrorCode::InvalidHashedPassword,
    IdentityErrorCode::UserLevelInvalid,
    IdentityErrorCode::UserHeightInvalid,
    IdentityErrorCode::UserWeightInvalid,
    IdentityErrorCode::OnboardingInvalidPrimaryModality,
    IdentityErrorCode::OnboardingNoModalitySelected,
    IdentityErrorCode::SocialProviderUnsupported,
    IdentityErrorCode::SocialEmailNotVerified,
    IdentityErrorCode::SocialEmailMissing,
]);

it('classifies authentication problems as authentication', function (IdentityErrorCode $code) {
    expect($code->kind())->toBe(ErrorKind::Authentication);
})->with([
    IdentityErrorCode::InvalidCredentials,
    IdentityErrorCode::RefreshTokenInvalid,
    IdentityErrorCode::RefreshTokenExpired,
    IdentityErrorCode::RefreshTokenReused,
    IdentityErrorCode::RefreshTokenAlreadyRotated,
    IdentityErrorCode::SocialAuthenticationFailed,
]);

it('classifies suspended accounts as forbidden', function () {
    expect(IdentityErrorCode::AccountSuspended->kind())->toBe(ErrorKind::Forbidden);
});
