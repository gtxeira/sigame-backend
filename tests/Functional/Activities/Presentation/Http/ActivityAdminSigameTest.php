<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

it('lets an admin sigame manage activities without being a group member', function () {
    $context = activityTestGroupWithMembers($this, 'activity-admin-sigame');
    authTestCreateAdmin($this, 'activity-admin-sigame@example.com');
    $adminToken = groupTestAccessToken($this, 'activity-admin-sigame@example.com');

    $create = $this->api->post(
        'app_activity_create',
        activityTestPayload([
            'startsAt' => (new DateTimeImmutable('+5 minutes'))->format(DateTimeInterface::RFC3339_EXTENDED),
            'estimatedDurationMinutes' => 60,
        ]),
        ['groupId' => $context['groupId']],
        groupTestServer($adminToken),
    );

    expect($create->getStatusCode())->toBe(Response::HTTP_CREATED);
    $activity = authTestBody($create)['data'];

    $list = $this->api->get(
        'app_activity_list',
        [],
        ['groupId' => $context['groupId']],
        groupTestServer($adminToken),
    );
    expect($list->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($list)['data'])->toHaveCount(1);

    $show = $this->api->get(
        'app_activity_show',
        [],
        ['id' => $activity['id']],
        groupTestServer($adminToken),
    );
    expect($show->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($show)['data']['canManage'])->toBeTrue();

    $update = $this->api->patch(
        'app_activity_update',
        ['title' => 'Corrida revisada pelo admin SIGAME'],
        ['id' => $activity['id']],
        groupTestServer($adminToken),
    );
    expect($update->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($update)['data']['title'])->toBe('Corrida revisada pelo admin SIGAME');

    $start = $this->api->post(
        'app_activity_start',
        [],
        ['id' => $activity['id']],
        groupTestServer($adminToken),
    );
    expect($start->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($start)['data']['status'])->toBe('started');

    $attendances = $this->api->get(
        'app_activity_attendance_list',
        [],
        ['id' => $activity['id']],
        groupTestServer($adminToken),
    );
    expect($attendances->getStatusCode())->toBe(Response::HTTP_OK);

    $finish = $this->api->post(
        'app_activity_finish',
        [],
        ['id' => $activity['id']],
        groupTestServer($adminToken),
    );
    expect($finish->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($finish)['data']['status'])->toBe('finished');

    $invalidate = $this->api->post(
        'app_activity_invalidate',
        ['reason' => 'Registro criado em teste administrativo.'],
        ['id' => $activity['id']],
        groupTestServer($adminToken),
    );
    expect($invalidate->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($invalidate)['data']['status'])->toBe('invalidated');

    $cancelTarget = activityTestCreate($this, $context['adminToken'], $context['groupId']);

    $cancel = $this->api->post(
        'app_activity_cancel',
        ['reason' => 'Cancelamento administrativo.'],
        ['id' => $cancelTarget['id']],
        groupTestServer($adminToken),
    );
    expect($cancel->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($cancel)['data']['status'])->toBe('cancelled');
});

it('keeps denying activity access to users outside the group', function () {
    $context = activityTestGroupWithMembers($this, 'activity-outsider');
    authTestCreateUser($this, 'activity-outsider@example.com');
    $outsiderToken = groupTestAccessToken($this, 'activity-outsider@example.com');

    $list = $this->api->get(
        'app_activity_list',
        [],
        ['groupId' => $context['groupId']],
        groupTestServer($outsiderToken),
    );
    expect($list->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toContain('ACTIVITY_GROUP_MEMBERSHIP_REQUIRED');

    $create = $this->api->post(
        'app_activity_create',
        activityTestPayload(),
        ['groupId' => $context['groupId']],
        groupTestServer($outsiderToken),
    );
    expect($create->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toContain('ACTIVITY_PERMISSION_DENIED');
});
