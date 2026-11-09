<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Policy;

/**
 * Limites e regras quantitativas dos grupos na V1.
 */
final class GroupPolicy
{
    public const int MIN_APPROVED_FOR_ACTIVE = 3;
    public const int MAX_APPROVED_MEMBERS = 5;
    public const int MAX_GROUPS_PER_USER = 3;
    public const int MIN_LEVEL_TO_CREATE = 2;

    public static function isActive(int $approvedMembers): bool
    {
        return $approvedMembers >= self::MIN_APPROVED_FOR_ACTIVE;
    }

    public static function isFull(int $approvedMembers): bool
    {
        return $approvedMembers >= self::MAX_APPROVED_MEMBERS;
    }

    public static function canJoin(int $approvedMembers): bool
    {
        return !self::isFull($approvedMembers);
    }
}
