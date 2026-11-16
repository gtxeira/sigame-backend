<?php

declare(strict_types=1);

namespace Tests\Integration\Administration\Infrastructure;

use App\Administration\Domain\Audit\Entity\AdminAuditEntry;
use App\Administration\Domain\Audit\Repository\AdminAuditRepositoryInterface;
use App\Administration\Domain\Audit\Repository\AdminAuditSearchCriteria;
use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;
use App\Shared\Domain\ValueObject\Instant;

function auditTestEntry(
    AdminAuditAction $action,
    AdminAuditTargetType $targetType,
    string $targetId,
    string $actorId,
    ?string $reason = null,
    ?Instant $occurredAt = null,
): AdminAuditEntry {
    return AdminAuditEntry::record(
        action: $action,
        targetType: $targetType,
        targetId: $targetId,
        targetLabel: 'Alvo de teste',
        actorId: $actorId,
        actorName: 'Ana Administradora',
        reason: $reason,
        occurredAt: $occurredAt ?? Instant::now(),
    );
}

it('appends entries and lists them from newest to oldest', function () {
    /** @var AdminAuditRepositoryInterface $repository */
    $repository = $this->service(AdminAuditRepositoryInterface::class);

    $first = auditTestEntry(
        AdminAuditAction::GroupArchived,
        AdminAuditTargetType::Group,
        '01a0d4ac-5d8d-7712-a6e3-f44a6cabc551',
        '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55e',
    );
    $second = auditTestEntry(
        AdminAuditAction::AccountSuspended,
        AdminAuditTargetType::User,
        '01a0d4ac-5d8d-7712-a6e3-f44a6cabc552',
        '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55e',
    );

    $repository->append($first);
    $repository->append($second);

    $page = $repository->search(new AdminAuditSearchCriteria());

    expect($page->total)->toBe(2)
        ->and($page->items)->toHaveCount(2)
        ->and($page->items[0]->id()->equals($second->id()))->toBeTrue()
        ->and($page->items[1]->id()->equals($first->id()))->toBeTrue();
});

it('filters the audit trail by action, target type, actor and period', function () {
    /** @var AdminAuditRepositoryInterface $repository */
    $repository = $this->service(AdminAuditRepositoryInterface::class);

    $actorA = '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55a';
    $actorB = '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55b';

    $repository->append(auditTestEntry(
        AdminAuditAction::GroupArchived,
        AdminAuditTargetType::Group,
        '01a0d4ac-5d8d-7712-a6e3-f44a6cabc551',
        $actorA,
        'Pausa.',
    ));
    $repository->append(auditTestEntry(
        AdminAuditAction::GroupDeleted,
        AdminAuditTargetType::Group,
        '01a0d4ac-5d8d-7712-a6e3-f44a6cabc552',
        $actorB,
        'Encerrado.',
    ));
    $repository->append(auditTestEntry(
        AdminAuditAction::AccountSuspended,
        AdminAuditTargetType::User,
        '01a0d4ac-5d8d-7712-a6e3-f44a6cabc553',
        $actorA,
        'Fraude.',
    ));

    expect($repository->search(new AdminAuditSearchCriteria(action: AdminAuditAction::GroupDeleted))->total)->toBe(1)
        ->and($repository->search(new AdminAuditSearchCriteria(targetType: AdminAuditTargetType::User))->total)->toBe(1)
        ->and($repository->search(new AdminAuditSearchCriteria(actorId: $actorA))->total)->toBe(2)
        ->and($repository->search(new AdminAuditSearchCriteria(
            from: Instant::now()->subHours(1)->value(),
            to: Instant::now()->addHours(1)->value(),
        ))->total)->toBe(3)
        ->and($repository->search(new AdminAuditSearchCriteria(
            from: Instant::now()->addHours(1)->value(),
        ))->total)->toBe(0);
});

it('paginates the audit trail', function () {
    /** @var AdminAuditRepositoryInterface $repository */
    $repository = $this->service(AdminAuditRepositoryInterface::class);

    foreach (range(1, 3) as $index) {
        $repository->append(auditTestEntry(
            AdminAuditAction::ActivityInvalidated,
            AdminAuditTargetType::Activity,
            sprintf('01a0d4ac-5d8d-7712-a6e3-f44a6cabc5%02d', $index),
            '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55e',
        ));
    }

    $page = $repository->search(new AdminAuditSearchCriteria(page: 2, perPage: 2));

    expect($page->total)->toBe(3)
        ->and($page->items)->toHaveCount(1);
});
