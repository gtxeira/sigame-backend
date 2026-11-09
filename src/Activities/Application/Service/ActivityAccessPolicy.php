<?php

declare(strict_types=1);

namespace App\Activities\Application\Service;

use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Group\GroupAccessSnapshot;
use App\Shared\Domain\Error\DomainError;

/**
 * Política de autorização de Activities sobre o grupo.
 *
 * Regras:
 * - membro aprovado: visualizar, inscrever-se e check-in próprio;
 * - supervisor: também inicia, finaliza, valida e lista presenças;
 * - admin do grupo: todas as operações;
 * - admin SIGAME: todas as operações de gestão/visualização.
 */
final readonly class ActivityAccessPolicy
{
    public function canView(?GroupAccessSnapshot $group, bool $isAdminSigame): ?DomainError
    {
        if (null === $group) {
            return ActivityErrors::groupNotFound();
        }

        if (!$group->isApprovedMember && !$isAdminSigame) {
            return ActivityErrors::groupMembershipRequired();
        }

        return null;
    }

    public function canParticipate(?GroupAccessSnapshot $group): ?DomainError
    {
        if (null === $group) {
            return ActivityErrors::groupNotFound();
        }

        if (!$group->isApprovedMember) {
            return ActivityErrors::groupMembershipRequired();
        }

        return null;
    }

    public function canManage(?GroupAccessSnapshot $group, bool $isAdminSigame): ?DomainError
    {
        if (null === $group) {
            return ActivityErrors::groupNotFound();
        }

        if (!$isAdminSigame && !$group->isAdmin) {
            return ActivityErrors::permissionDenied();
        }

        return null;
    }

    public function canOperate(?GroupAccessSnapshot $group, bool $isAdminSigame): ?DomainError
    {
        if (null === $group) {
            return ActivityErrors::groupNotFound();
        }

        if (!$isAdminSigame && !$group->isAdmin && !$group->isSupervisor) {
            return ActivityErrors::permissionDenied();
        }

        return null;
    }

    public function canInvalidate(?GroupAccessSnapshot $group, bool $isAdminSigame): ?DomainError
    {
        if (null === $group) {
            return ActivityErrors::groupNotFound();
        }

        if (!$isAdminSigame && !$group->isAdmin) {
            return ActivityErrors::permissionDenied();
        }

        return null;
    }

    public function groupAcceptsActivities(?GroupAccessSnapshot $group): ?DomainError
    {
        if (null === $group) {
            return ActivityErrors::groupNotFound();
        }

        if (!$group->acceptsActivities) {
            return ActivityErrors::groupNotActive();
        }

        return null;
    }
}
