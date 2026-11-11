<?php

declare(strict_types=1);

use App\Activities\Domain\Error\ActivityErrorCode;
use App\Groups\Domain\Error\GroupErrorCode;
use App\Identity\Domain\Error\IdentityErrorCode;
use App\Notifications\Domain\Error\NotificationErrorCode;
use App\Shared\Domain\Error\ErrorKind;
use App\Shared\Domain\Error\SharedErrorCode;
use App\Shared\Domain\Error\ValidationErrorCode;
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
 * @return list<class-string<SharedErrorCode|IdentityErrorCode|GroupErrorCode|ActivityErrorCode|NotificationErrorCode|ValidationErrorCode|ApiErrorCode>>
 */
function errorCodeCatalogs(): array
{
    return [
        SharedErrorCode::class,
        IdentityErrorCode::class,
        GroupErrorCode::class,
        ActivityErrorCode::class,
        NotificationErrorCode::class,
        ValidationErrorCode::class,
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

it('classifies every domain error code', function (SharedErrorCode|IdentityErrorCode|GroupErrorCode|ActivityErrorCode|NotificationErrorCode $code) {
    expect(ErrorKind::cases())->toContain($code->kind());
})->with(fn (): array => [
    ...SharedErrorCode::cases(),
    ...IdentityErrorCode::cases(),
    ...GroupErrorCode::cases(),
    ...ActivityErrorCode::cases(),
    ...NotificationErrorCode::cases(),
]);

it('classifies state conflicts as conflict', function (IdentityErrorCode|GroupErrorCode $code) {
    expect($code->kind())->toBe(ErrorKind::Conflict);
})->with([
    IdentityErrorCode::UserCpfAlreadyExists,
    IdentityErrorCode::EmailAlreadyExists,
    IdentityErrorCode::SocialIdentityAlreadyLinked,
    IdentityErrorCode::OnboardingAlreadyCompleted,
    IdentityErrorCode::EmailAlreadyVerified,
    IdentityErrorCode::UserCpfAlreadySet,
    IdentityErrorCode::RefreshInProgress,
    GroupErrorCode::GroupMembershipSuspended,
    GroupErrorCode::GroupMemberLimitBelowApproved,
]);

it('classifies input problems as validation', function (SharedErrorCode|IdentityErrorCode|GroupErrorCode|ValidationErrorCode $code) {
    expect($code->kind())->toBe(ErrorKind::Validation);
})->with([
    SharedErrorCode::InvalidUuid,
    IdentityErrorCode::UserLevelInvalid,
    IdentityErrorCode::OnboardingInvalidPrimaryModality,
    IdentityErrorCode::OnboardingNoModalitySelected,
    IdentityErrorCode::SocialProviderUnsupported,
    IdentityErrorCode::SocialEmailNotVerified,
    IdentityErrorCode::SocialEmailMissing,
    ...ValidationErrorCode::cases(),
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
    expect(IdentityErrorCode::AccountSuspended->kind())->toBe(ErrorKind::Forbidden)
        ->and(IdentityErrorCode::CannotSuspendAdmin->kind())->toBe(ErrorKind::Forbidden);
});

it('classifies missing users and notifications as not found', function () {
    expect(IdentityErrorCode::UserNotFound->kind())->toBe(ErrorKind::NotFound)
        ->and(NotificationErrorCode::NotificationNotFound->kind())->toBe(ErrorKind::NotFound);
});
