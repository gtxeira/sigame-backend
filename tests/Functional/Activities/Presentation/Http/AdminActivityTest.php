<?php

declare(strict_types=1);

namespace Tests\Functional\Activities\Presentation\Http;

it('lists activities from every group for the administration', function () {
    $context = activityTestGroupWithMembers($this, 'admin-activities');
    $activity = activityTestCreate($this, $context['adminToken'], $context['groupId']);

    authTestCreateAdmin($this, 'admin-activities-sigame@example.com');
    $adminToken = groupTestAccessToken($this, 'admin-activities-sigame@example.com');
    $server = groupTestServer($adminToken);

    $response = $this->api->get('app_admin_activity_list', server: $server);

    expect($response->getStatusCode())->toBe(200);

    $body = authTestBody($response);
    expect($body['meta']['pagination']['total'])->toBe(1)
        ->and($body['data'][0]['id'])->toBe($activity['id'])
        ->and($body['data'][0]['groupName'])->toBe('Corrida no Parque')
        ->and($body['data'][0]['status'])->toBe('scheduled');

    $filtered = $this->api->get(
        'app_admin_activity_list',
        ['groupId' => $context['groupId'], 'title' => 'corrida', 'status' => 'scheduled'],
        server: $server,
    );
    expect(authTestBody($filtered)['meta']['pagination']['total'])->toBe(1);

    $empty = $this->api->get('app_admin_activity_list', ['status' => 'finished'], server: $server);
    expect(authTestBody($empty)['meta']['pagination']['total'])->toBe(0);
});

it('lets a SIGAME admin cancel an activity and records the audit entry', function () {
    $context = activityTestGroupWithMembers($this, 'admin-activities-cancel');
    $activity = activityTestCreate($this, $context['adminToken'], $context['groupId']);

    authTestCreateAdmin($this, 'admin-activities-cancel-sigame@example.com');
    $adminToken = groupTestAccessToken($this, 'admin-activities-cancel-sigame@example.com');
    $server = groupTestServer($adminToken);

    $cancel = $this->api->post(
        'app_activity_cancel',
        ['reason' => 'Chuva forte no horário.'],
        ['id' => $activity['id']],
        $server,
    );

    expect($cancel->getStatusCode())->toBe(200)
        ->and(authTestBody($cancel)['data']['status'])->toBe('cancelled');

    $audit = $this->api->get('app_admin_audit_list', ['targetType' => 'ACTIVITY'], server: $server);
    $entries = authTestBody($audit)['data'];

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['action'])->toBe('ACTIVITY_CANCELLED')
        ->and($entries[0]['targetId'])->toBe($activity['id'])
        ->and($entries[0]['targetLabel'])->toBe('Corrida no Parque')
        ->and($entries[0]['reason'])->toBe('Chuva forte no horário.');
});

it('does not allow a regular user to list activities globally', function () {
    groupTestCreateUser($this, 'regular-activities@example.com');
    $token = groupTestAccessToken($this, 'regular-activities@example.com');

    $response = $this->api->get('app_admin_activity_list', server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(403);
});
