<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Policy;

/**
 * Limites e regras quantitativas dos grupos na V1.
 *
 * O limite de membros aprovados é opcional por grupo: `null` significa
 * ilimitado. Quando informado, respeita o mínimo configurável.
 */
final class GroupPolicy
{
    public const int MIN_APPROVED_FOR_ACTIVE = 3;
    public const int MIN_CONFIGURABLE_MEMBERS = self::MIN_APPROVED_FOR_ACTIVE;
    public const int MAX_GROUPS_PER_USER = 3;
    public const int MIN_LEVEL_TO_CREATE = 2;

    public static function isActive(int $approvedMembers): bool
    {
        return $approvedMembers >= self::MIN_APPROVED_FOR_ACTIVE;
    }

    public static function isFull(int $approvedMembers, ?int $maxMembers): bool
    {
        return null !== $maxMembers && $approvedMembers >= $maxMembers;
    }

    public static function canJoin(int $approvedMembers, ?int $maxMembers): bool
    {
        return !self::isFull($approvedMembers, $maxMembers);
    }
}
