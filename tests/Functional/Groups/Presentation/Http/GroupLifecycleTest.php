<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

/**
 * @return list<string>
 */
function lifecycleNotificationTypes(Tests\FunctionalTestCase $test, string $token, string $category = 'GROUP'): array
{
    $response = $test->api->get(
        'app_notifications_list',
        ['category' => $category],
        server: groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    return array_map(
        static fn (array $item): string => (string) $item['type'],
        authTestBody($response)['data'],
    );
}

it('rejects a duplicate group with the same creator, name and modality', function () {
    groupTestCreateUser($this, 'groups-duplicate@example.com');
    $token = groupTestAccessToken($this, 'groups-duplicate@example.com');

    groupTestCreate($this, $token, ['name' => 'Corrida no Parque']);

    $response = $this->api->post(
        'app_group_create',
        groupTestPayload(['name' => 'corrida no parque']),
        server: groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_DUPLICATE']);
});

it('rejects a second forming group for the same creator', function () {
    groupTestCreateUser($this, 'groups-forming@example.com');
    $token = groupTestAccessToken($this, 'groups-forming@example.com');

    groupTestCreate($this, $token, ['name' => 'Corrida no Parque']);

    $response = $this->api->post(
        'app_group_create',
        groupTestPayload(['name' => 'Pedal da Serra', 'modality' => 'ciclismo']),
        server: groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_FORMING_LIMIT_REACHED']);
});

it('rejects a membership request from a level 1 user', function () {
    groupTestCreateUser($this, 'groups-join-level@example.com');
    $adminToken = groupTestAccessToken($this, 'groups-join-level@example.com');
    $group = groupTestCreate($this, $adminToken);

    authTestCreateUser($this, 'groups-join-level1@example.com');
    $token = authTestAccessToken(authTestLogin($this, 'groups-join-level1@example.com'));

    $response = $this->api->post(
        'app_group_membership_request',
        [],
        ['id' => $group['id']],
        groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['GROUP_LEVEL_INSUFFICIENT']);
});

it('withdraws a pending request when the requester leaves', function () {
    groupTestCreateUser($this, 'groups-withdraw-admin@example.com');
    $adminToken = groupTestAccessToken($this, 'groups-withdraw-admin@example.com');
    $group = groupTestCreate($this, $adminToken);

    groupTestCreateUser($this, 'groups-withdraw-member@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-withdraw-member@example.com');
    $membershipId = groupTestRequestMembership($this, $memberToken, $group['id']);

    $leave = $this->api->delete(
        'app_group_membership_leave',
        [],
        ['id' => $group['id']],
        groupTestServer($memberToken),
    );

    expect($leave->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($leave)['data']['removed'])->toBeTrue();

    $memberships = $this->api->get(
        'app_group_membership_list',
        [],
        ['id' => $group['id']],
        groupTestServer($adminToken),
    );

    $item = null;

    foreach (authTestBody($memberships)['data'] as $candidate) {
        if ($candidate['id'] === $membershipId) {
            $item = $candidate;
        }
    }

    expect($item)->not->toBeNull()
        ->and($item['status'])->toBe('removed')
        ->and($item['removalReason'])->toBe('Solicitação cancelada pelo usuário.');

    $again = $this->api->delete(
        'app_group_membership_leave',
        [],
        ['id' => $group['id']],
        groupTestServer($memberToken),
    );

    expect($again->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($again)['data']['removed'])->toBeFalse();
});

it('lets an approved member leave but never the last admin', function () {
    groupTestCreateUser($this, 'groups-leave-admin@example.com');
    $adminToken = groupTestAccessToken($this, 'groups-leave-admin@example.com');
    $group = groupTestCreate($this, $adminToken);

    groupTestCreateUser($this, 'groups-leave-member@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-leave-member@example.com');
    $membershipId = groupTestRequestMembership($this, $memberToken, $group['id']);

    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $group['id'], 'membershipId' => $membershipId],
        groupTestServer($adminToken),
    );

    $leave = $this->api->delete(
        'app_group_membership_leave',
        [],
        ['id' => $group['id']],
        groupTestServer($memberToken),
    );

    expect($leave->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($leave)['data']['removed'])->toBeTrue();

    $myGroups = $this->api->get('app_me_groups', server: groupTestServer($memberToken));
    expect(authTestBody($myGroups)['data'])->toBe([]);

    $lastAdmin = $this->api->delete(
        'app_group_membership_leave',
        [],
        ['id' => $group['id']],
        groupTestServer($adminToken),
    );

    expect($lastAdmin->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_LAST_ADMIN_REQUIRED']);
});

it('archives a group, hides it from the search and notifies members and the owner', function () {
    groupTestCreateUser($this, 'groups-archive-owner@example.com');
    $ownerToken = groupTestAccessToken($this, 'groups-archive-owner@example.com');
    $owner = authTestUserRepository($this)->findByEmail(
        \App\Shared\Domain\ValueObject\Email::fromString('groups-archive-owner@example.com')->value(),
    );
    $group = groupTestCreate($this, $ownerToken);

    groupTestCreateUser($this, 'groups-archive-admin@example.com');
    $adminToken = groupTestAccessToken($this, 'groups-archive-admin@example.com');
    $adminMembership = groupTestRequestMembership($this, $adminToken, $group['id']);
    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $group['id'], 'membershipId' => $adminMembership],
        groupTestServer($ownerToken),
    );
    $this->api->patch(
        'app_group_membership_role',
        ['role' => 'admin'],
        ['id' => $group['id'], 'membershipId' => $adminMembership],
        groupTestServer($ownerToken),
    );

    groupTestCreateUser($this, 'groups-archive-member@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-archive-member@example.com');
    $memberMembership = groupTestRequestMembership($this, $memberToken, $group['id']);
    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $group['id'], 'membershipId' => $memberMembership],
        groupTestServer($ownerToken),
    );

    $archive = $this->api->post(
        'app_group_archive',
        ['reason' => 'Pausa nas atividades.'],
        ['id' => $group['id']],
        groupTestServer($adminToken),
    );

    expect($archive->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($archive)['data']['archived'])->toBeTrue();

    groupTestCreateUser($this, 'groups-archive-outsider@example.com');
    $outsiderToken = groupTestAccessToken($this, 'groups-archive-outsider@example.com');

    $list = $this->api->get('app_group_list', server: groupTestServer($outsiderToken));
    expect(authTestBody($list)['data'])->toBe([]);

    $show = $this->api->get(
        'app_group_show',
        [],
        ['id' => $group['id']],
        groupTestServer($outsiderToken),
    );
    expect($show->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);

    $myGroups = $this->api->get('app_me_groups', server: groupTestServer($ownerToken));
    $ownerGroup = null;

    foreach (authTestBody($myGroups)['data'] as $candidate) {
        if ($candidate['id'] === $group['id']) {
            $ownerGroup = $candidate;
        }
    }

    expect($ownerGroup)->not->toBeNull()
        ->and($ownerGroup['archived'])->toBeTrue()
        ->and($ownerGroup['archivedAt'])->not->toBeNull();

    expect(lifecycleNotificationTypes($this, $ownerToken))->toContain('GROUP_ARCHIVED_OWNER')
        ->and(lifecycleNotificationTypes($this, $memberToken))->toContain('GROUP_ARCHIVED');

    $deliveries = authTestEntityManager($this)->getConnection()->fetchAllAssociative(
        'SELECT channel, status FROM notification_deliveries',
    );

    expect($deliveries)->toHaveCount(1)
        ->and($deliveries[0]['channel'])->toBe('EMAIL')
        ->and($deliveries[0]['status'])->toBe('SENT');

    $unarchive = $this->api->post(
        'app_group_unarchive',
        [],
        ['id' => $group['id']],
        groupTestServer($adminToken),
    );

    expect($unarchive->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($unarchive)['data']['archived'])->toBeFalse();

    $listAfter = $this->api->get('app_group_list', server: groupTestServer($outsiderToken));
    expect(authTestBody($listAfter)['data'])->toHaveCount(1);
});

it('deletes a group logically and notifies every participant', function () {
    groupTestCreateUser($this, 'groups-delete-owner@example.com');
    $ownerToken = groupTestAccessToken($this, 'groups-delete-owner@example.com');
    $group = groupTestCreate($this, $ownerToken);

    groupTestCreateUser($this, 'groups-delete-member@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-delete-member@example.com');
    $membershipId = groupTestRequestMembership($this, $memberToken, $group['id']);
    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $group['id'], 'membershipId' => $membershipId],
        groupTestServer($ownerToken),
    );

    $delete = $this->api->delete(
        'app_group_delete',
        ['reason' => 'Grupo encerrado.'],
        ['id' => $group['id']],
        groupTestServer($ownerToken),
    );

    expect($delete->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($delete)['data']['deleted'])->toBeTrue();

    $show = $this->api->get(
        'app_group_show',
        [],
        ['id' => $group['id']],
        groupTestServer($ownerToken),
    );

    expect($show->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);

    $myGroups = $this->api->get('app_me_groups', server: groupTestServer($ownerToken));
    expect(authTestBody($myGroups)['data'])->toBe([]);

    expect(lifecycleNotificationTypes($this, $ownerToken))->toContain('GROUP_DELETED')
        ->and(lifecycleNotificationTypes($this, $memberToken))->toContain('GROUP_DELETED');
});

it('rejects renaming a group to a name already used by the creator', function () {
    groupTestCreateUser($this, 'groups-rename@example.com');
    $token = groupTestAccessToken($this, 'groups-rename@example.com');

    $first = groupTestCreate($this, $token, ['name' => 'Corrida no Parque']);

    foreach (['groups-rename-member1@example.com', 'groups-rename-member2@example.com'] as $email) {
        groupTestCreateUser($this, $email);
        $memberToken = groupTestAccessToken($this, $email);
        $membershipId = groupTestRequestMembership($this, $memberToken, $first['id']);

        $this->api->post(
            'app_group_membership_approve',
            [],
            ['id' => $first['id'], 'membershipId' => $membershipId],
            groupTestServer($token),
        );
    }

    $second = groupTestCreate($this, $token, ['name' => 'Pedal da Serra']);

    $rename = $this->api->patch(
        'app_group_update',
        ['name' => 'Corrida no Parque'],
        ['id' => $second['id']],
        groupTestServer($token),
    );

    expect($rename->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_DUPLICATE']);
});

it('rejects unarchiving when the creator already recreated the group', function () {
    groupTestCreateUser($this, 'groups-unarchive-duplicate@example.com');
    $token = groupTestAccessToken($this, 'groups-unarchive-duplicate@example.com');

    $original = groupTestCreate($this, $token, ['name' => 'Corrida no Parque']);
    $this->api->post(
        'app_group_archive',
        [],
        ['id' => $original['id']],
        groupTestServer($token),
    );

    groupTestCreate($this, $token, ['name' => 'Corrida no Parque']);

    $unarchive = $this->api->post(
        'app_group_unarchive',
        [],
        ['id' => $original['id']],
        groupTestServer($token),
    );

    expect($unarchive->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_DUPLICATE']);
});

it('cancels scheduled activities when the group is deleted', function () {
    $context = activityTestGroupWithMembers($this, 'groups-lifecycle-activities');
    $activity = activityTestCreate(
        $this,
        $context['adminToken'],
        $context['groupId'],
        ['startsAt' => (new DateTimeImmutable('+2 days'))->format(DateTimeInterface::RFC3339_EXTENDED)],
    );

    $delete = $this->api->delete(
        'app_group_delete',
        ['reason' => 'Grupo encerrado.'],
        ['id' => $context['groupId']],
        groupTestServer($context['adminToken']),
    );

    expect($delete->getStatusCode())->toBe(Response::HTTP_OK);

    $show = $this->api->get(
        'app_activity_show',
        [],
        ['id' => $activity['id']],
        groupTestServer($context['adminToken']),
    );

    expect(authTestBody($show)['data']['status'])->toBe('cancelled');
});
