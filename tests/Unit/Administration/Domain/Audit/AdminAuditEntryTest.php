<?php

declare(strict_types=1);

namespace Tests\Unit\Administration\Domain\Audit;

use App\Administration\Domain\Audit\Entity\AdminAuditEntry;
use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;
use App\Shared\Domain\ValueObject\Instant;

it('records an append-only audit entry with a label snapshot', function () {
    $occurredAt = Instant::now();

    $entry = AdminAuditEntry::record(
        action: AdminAuditAction::GroupArchived,
        targetType: AdminAuditTargetType::Group,
        targetId: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f',
        targetLabel: 'Corrida no Parque',
        actorId: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55e',
        actorName: 'Ana Administradora',
        reason: 'Grupo pausou as atividades.',
        occurredAt: $occurredAt,
    );

    expect($entry->action())->toBe(AdminAuditAction::GroupArchived)
        ->and($entry->targetType())->toBe(AdminAuditTargetType::Group)
        ->and($entry->targetLabel())->toBe('Corrida no Parque')
        ->and($entry->actorName())->toBe('Ana Administradora')
        ->and($entry->reason())->toBe('Grupo pausou as atividades.')
        ->and($entry->occurredAt())->toBe($occurredAt)
        ->and($entry->id()->value())->not->toBeEmpty();
});

it('truncates oversized labels and reasons to the column limits', function () {
    $entry = AdminAuditEntry::record(
        action: AdminAuditAction::GroupDeleted,
        targetType: AdminAuditTargetType::Group,
        targetId: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f',
        targetLabel: str_repeat('a', 200),
        actorId: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55e',
        actorName: str_repeat('b', 200),
        reason: str_repeat('c', 600),
        occurredAt: Instant::now(),
    );

    expect(mb_strlen($entry->targetLabel()))->toBe(160)
        ->and(mb_strlen($entry->actorName()))->toBe(160)
        ->and(mb_strlen((string) $entry->reason()))->toBe(500);
});

it('accepts a null reason for idempotent actions', function () {
    $entry = AdminAuditEntry::record(
        action: AdminAuditAction::GroupUnarchived,
        targetType: AdminAuditTargetType::Group,
        targetId: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f',
        targetLabel: 'Corrida no Parque',
        actorId: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55e',
        actorName: 'Ana Administradora',
        reason: null,
        occurredAt: Instant::now(),
    );

    expect($entry->reason())->toBeNull();
});
