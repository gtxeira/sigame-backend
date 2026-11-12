<?php

declare(strict_types=1);

namespace App\Groups\Domain\Error;

use App\Shared\Domain\Error\ErrorKind;

/**
 * Catálogo de códigos de erro do módulo Groups.
 *
 * O valor de cada caso é contrato público com o cliente da API:
 * alterá-lo é breaking change; renomear o caso não é.
 */
enum GroupErrorCode: string
{
    case GroupNotFound = 'GROUP_NOT_FOUND';
    case MembershipNotFound = 'GROUP_MEMBERSHIP_NOT_FOUND';
    case GroupMemberLimitBelowApproved = 'GROUP_MEMBER_LIMIT_BELOW_APPROVED';
    case GroupSupervisorNotMember = 'GROUP_SUPERVISOR_NOT_MEMBER';
    case GroupLevelInsufficient = 'GROUP_LEVEL_INSUFFICIENT';
    case GroupAdminRequired = 'GROUP_ADMIN_REQUIRED';
    case GroupOwnerRequired = 'GROUP_OWNER_REQUIRED';
    case GroupOwnerTransferRequired = 'GROUP_OWNER_TRANSFER_REQUIRED';
    case GroupLimitReached = 'GROUP_LIMIT_REACHED';
    case GroupFormingLimitReached = 'GROUP_FORMING_LIMIT_REACHED';
    case GroupDuplicate = 'GROUP_DUPLICATE';
    case GroupArchived = 'GROUP_ARCHIVED';
    case GroupFull = 'GROUP_FULL';
    case GroupMembershipAlreadyExists = 'GROUP_MEMBERSHIP_ALREADY_EXISTS';
    case GroupMembershipNotPending = 'GROUP_MEMBERSHIP_NOT_PENDING';
    case GroupMembershipNotApproved = 'GROUP_MEMBERSHIP_NOT_APPROVED';
    case GroupMembershipSuspended = 'GROUP_MEMBERSHIP_SUSPENDED';
    case GroupLastAdminRequired = 'GROUP_LAST_ADMIN_REQUIRED';

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::GroupNotFound,
            self::MembershipNotFound => ErrorKind::NotFound,

            self::GroupSupervisorNotMember => ErrorKind::Validation,

            self::GroupLevelInsufficient,
            self::GroupAdminRequired,
            self::GroupOwnerRequired => ErrorKind::Forbidden,

            self::GroupLimitReached,
            self::GroupFormingLimitReached,
            self::GroupDuplicate,
            self::GroupArchived,
            self::GroupFull,
            self::GroupOwnerTransferRequired,
            self::GroupMemberLimitBelowApproved,
            self::GroupMembershipAlreadyExists,
            self::GroupMembershipNotPending,
            self::GroupMembershipNotApproved,
            self::GroupMembershipSuspended,
            self::GroupLastAdminRequired => ErrorKind::Conflict,
        };
    }
}
