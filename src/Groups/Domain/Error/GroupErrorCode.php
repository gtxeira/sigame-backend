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
    case GroupNameInvalid = 'GROUP_NAME_INVALID';
    case GroupDescriptionInvalid = 'GROUP_DESCRIPTION_INVALID';
    case GroupCommunicationLinkInvalid = 'GROUP_COMMUNICATION_LINK_INVALID';
    case GroupMemberLimitInvalid = 'GROUP_MEMBER_LIMIT_INVALID';
    case GroupMemberLimitBelowApproved = 'GROUP_MEMBER_LIMIT_BELOW_APPROVED';
    case GroupRemovalReasonRequired = 'GROUP_REMOVAL_REASON_REQUIRED';
    case GroupSupervisorNotMember = 'GROUP_SUPERVISOR_NOT_MEMBER';
    case GroupLevelInsufficient = 'GROUP_LEVEL_INSUFFICIENT';
    case GroupAdminRequired = 'GROUP_ADMIN_REQUIRED';
    case GroupLimitReached = 'GROUP_LIMIT_REACHED';
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

            self::GroupNameInvalid,
            self::GroupDescriptionInvalid,
            self::GroupCommunicationLinkInvalid,
            self::GroupMemberLimitInvalid,
            self::GroupRemovalReasonRequired,
            self::GroupSupervisorNotMember => ErrorKind::Validation,

            self::GroupLevelInsufficient,
            self::GroupAdminRequired => ErrorKind::Forbidden,

            self::GroupLimitReached,
            self::GroupFull,
            self::GroupMemberLimitBelowApproved,
            self::GroupMembershipAlreadyExists,
            self::GroupMembershipNotPending,
            self::GroupMembershipNotApproved,
            self::GroupMembershipSuspended,
            self::GroupLastAdminRequired => ErrorKind::Conflict,
        };
    }
}
