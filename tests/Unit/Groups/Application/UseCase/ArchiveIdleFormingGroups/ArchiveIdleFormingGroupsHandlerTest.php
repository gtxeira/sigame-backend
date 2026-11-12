<?php

declare(strict_types=1);

use App\Groups\Application\UseCase\ArchiveIdleFormingGroups\ArchiveIdleFormingGroupsHandler;
use App\Groups\Application\UseCase\ArchiveIdleFormingGroups\ArchiveIdleFormingGroupsMessage;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupArchived;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Domain\ValueObject\Modality;
use Mockery as m;
use Symfony\Component\Clock\MockClock;

function idleFormingGroup(): Group
{
    return Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: MemberUserId::generate(),
    );
}

it('archives idle forming groups using the configured window', function () {
    $group = idleFormingGroup();
    $clock = new MockClock('2030-01-31T12:00:00+00:00');

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('findIdleFormingGroupIds')
        ->once()
        ->with(m::on(
            static fn (Instant $since): bool => '2030-01-01T12:00:00' === $since->format('Y-m-d\TH:i:s'),
        ))
        ->andReturn([$group->id()]);
    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('save')->once()->with($group);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::on(
        static fn (GroupArchived $event): bool => $event->automatic() && null === $event->archivedBy(),
    ));

    $archived = (new ArchiveIdleFormingGroupsHandler($repository, $dispatcher, $clock))(
        new ArchiveIdleFormingGroupsMessage(),
    );

    expect($archived)->toBe(1)
        ->and($group->isArchived())->toBeTrue()
        ->and($group->archiveReason())->toBe('Arquivado automaticamente por inatividade.');
});

it('skips groups that are already active or recently touched', function () {
    $clock = new MockClock('2020-01-01T00:00:00+00:00');
    $active = idleFormingGroup();

    for ($index = 0; $index < 2; ++$index) {
        $membership = $active->requestMembership(MemberUserId::generate())->value();
        $active->approveMembership($membership->id(), $active->createdBy(), 0);
    }

    $recent = idleFormingGroup();

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('findIdleFormingGroupIds')->once()->andReturn([
        $active->id(),
        $recent->id(),
    ]);
    $repository->shouldReceive('transactional')->twice()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->twice()->andReturn($active, $recent);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $archived = (new ArchiveIdleFormingGroupsHandler($repository, $dispatcher, $clock))(
        new ArchiveIdleFormingGroupsMessage(),
    );

    expect($archived)->toBe(0)
        ->and($active->isArchived())->toBeFalse()
        ->and($recent->isArchived())->toBeFalse();
});
