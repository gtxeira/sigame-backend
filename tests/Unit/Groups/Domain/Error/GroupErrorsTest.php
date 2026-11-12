<?php

declare(strict_types=1);

use App\Groups\Domain\Error\GroupErrorCode;
use App\Groups\Domain\Error\GroupErrors;
use App\Shared\Domain\Error\ErrorKind;

it('builds the public group errors with messages and kinds', function () {
    $errors = [
        GroupErrors::groupNotFound(),
        GroupErrors::membershipNotFound(),
        GroupErrors::memberLimitBelowApproved(2, 3),
        GroupErrors::supervisorNotMember(),
        GroupErrors::levelInsufficient(2),
        GroupErrors::adminRequired(),
        GroupErrors::limitReached(5),
        GroupErrors::formingLimitReached(1),
        GroupErrors::duplicateGroup(),
        GroupErrors::groupArchived(),
        GroupErrors::groupFull(5),
        GroupErrors::membershipAlreadyExists(),
        GroupErrors::membershipNotPending(),
        GroupErrors::membershipNotApproved(),
        GroupErrors::membershipSuspended(),
        GroupErrors::lastAdminRequired(),
    ];

    $codes = array_map(static fn ($error): string => $error->code, $errors);
    $expected = array_map(static fn (GroupErrorCode $code): string => $code->value, GroupErrorCode::cases());

    expect($codes)->toEqualCanonicalizing($expected);

    foreach ($errors as $error) {
        expect($error->message)->not->toBeEmpty()
            ->and(GroupErrorCode::from($error->code)->kind())->toBe($error->kind);
    }
});

it('classifies group errors by nature', function () {
    expect(GroupErrorCode::GroupNotFound->kind())->toBe(ErrorKind::NotFound)
        ->and(GroupErrorCode::GroupAdminRequired->kind())->toBe(ErrorKind::Forbidden)
        ->and(GroupErrorCode::GroupMemberLimitBelowApproved->kind())->toBe(ErrorKind::Conflict)
        ->and(GroupErrorCode::GroupFull->kind())->toBe(ErrorKind::Conflict);
});
