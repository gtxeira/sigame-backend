<?php

declare(strict_types=1);

use Tests\FunctionalTestCase;
use Tests\Support\RecordingRealtimePublisher;

function notificationTestRealtimePublisher(FunctionalTestCase $test): RecordingRealtimePublisher
{
    /** @var RecordingRealtimePublisher $publisher */
    /* @phpstan-ignore-next-line */
    $publisher = $test->api->getKernelBrowser()->getContainer()->get(RecordingRealtimePublisher::class);

    return $publisher;
}

function notificationTestClear(FunctionalTestCase $test, string $token): void
{
    $response = $test->api->post('app_notifications_read_all', [], [], groupTestServer($token));
    expect($response->getStatusCode())->toBe(200);
}

it('creates an unread notification for every group admin when someone requests membership', function () {
    groupTestCreateUser($this, 'admin-notification@example.com');
    $adminToken = groupTestAccessToken($this, 'admin-notification@example.com');
    $group = groupTestCreate($this, $adminToken);
    notificationTestClear($this, $adminToken);

    groupTestCreateUser($this, 'requester-notification@example.com');
    $requesterToken = groupTestAccessToken($this, 'requester-notification@example.com');

    groupTestRequestMembership($this, $requesterToken, $group['id']);

    $list = $this->api->get(
        'app_notifications_list',
        ['status' => 'unread'],
        server: groupTestServer($adminToken),
    );

    expect($list->getStatusCode())->toBe(200);
    $items = authTestBody($list)['data'];
    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('MEMBERSHIP_REQUESTED')
        ->and($items[0]['category'])->toBe('GROUP')
        ->and($items[0]['read'])->toBeFalse()
        ->and($items[0]['data']['groupId'])->toBe($group['id']);

    $count = $this->api->get(
        'app_notifications_unread_count',
        server: groupTestServer($adminToken),
    );
    expect(authTestBody($count)['data']['total'])->toBe(1);

    $publisher = notificationTestRealtimePublisher($this);
    $admin = authTestUserRepository($this)->findByEmail(
        \App\Shared\Domain\ValueObject\Email::fromString('admin-notification@example.com')->value(),
    );
    expect($publisher->lastFor($admin->id()->value())['event'])->toBe('notification.created');

    $requesterList = $this->api->get(
        'app_notifications_list',
        ['category' => 'GROUP'],
        server: groupTestServer($requesterToken),
    );
    expect(authTestBody($requesterList)['data'])->toBe([]);
});

it('marks one notification and all notifications as read', function () {
    groupTestCreateUser($this, 'admin-read@example.com');
    $adminToken = groupTestAccessToken($this, 'admin-read@example.com');
    $group = groupTestCreate($this, $adminToken);
    notificationTestClear($this, $adminToken);

    groupTestCreateUser($this, 'requester-read@example.com');
    $requesterToken = groupTestAccessToken($this, 'requester-read@example.com');
    groupTestRequestMembership($this, $requesterToken, $group['id']);

    $list = $this->api->get('app_notifications_list', [], server: groupTestServer($adminToken));
    $notificationId = authTestBody($list)['data'][0]['id'];

    $markRead = $this->api->post(
        'app_notifications_read',
        [],
        ['id' => $notificationId],
        groupTestServer($adminToken),
    );
    expect($markRead->getStatusCode())->toBe(200)
        ->and(authTestBody($markRead)['data']['read'])->toBeTrue();

    $again = $this->api->post(
        'app_notifications_read',
        [],
        ['id' => $notificationId],
        groupTestServer($adminToken),
    );
    expect($again->getStatusCode())->toBe(200);

    $count = $this->api->get('app_notifications_unread_count', server: groupTestServer($adminToken));
    expect(authTestBody($count)['data']['total'])->toBe(0);

    $markAll = $this->api->post('app_notifications_read_all', [], [], groupTestServer($adminToken));
    expect($markAll->getStatusCode())->toBe(200);
});

it('does not allow reading a notification from another user', function () {
    groupTestCreateUser($this, 'admin-isolation@example.com');
    $adminToken = groupTestAccessToken($this, 'admin-isolation@example.com');
    $group = groupTestCreate($this, $adminToken);
    notificationTestClear($this, $adminToken);

    groupTestCreateUser($this, 'requester-isolation@example.com');
    $requesterToken = groupTestAccessToken($this, 'requester-isolation@example.com');
    groupTestRequestMembership($this, $requesterToken, $group['id']);

    $list = $this->api->get('app_notifications_list', [], server: groupTestServer($adminToken));
    $notificationId = authTestBody($list)['data'][0]['id'];

    $response = $this->api->post(
        'app_notifications_read',
        [],
        ['id' => $notificationId],
        groupTestServer($requesterToken),
    );

    expect($response->getStatusCode())->toBe(404)
        ->and($this->api->errorCodes())->toContain('NOTIFICATION_NOT_FOUND');
});

it('returns a realtime subscription authorization for the authenticated user', function () {
    authTestCreateUser($this, 'realtime-user@example.com');
    $token = authTestAccessToken(authTestLogin($this, 'realtime-user@example.com'));

    $response = $this->api->get('app_notifications_realtime', server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(200);
    $body = authTestBody($response)['data'];
    expect($body['transport'])->toBe('mercure')
        ->and($body['subscribeUrl'])->toContain('mercure.test')
        ->and($body['token'])->toStartWith('test-token-');
});

it('notifies approved group members when an activity is created, except the creator', function () {
    $scenario = activityTestGroupWithMembers($this, 'notify-activity');
    $adminToken = $scenario['adminToken'];
    $groupId = $scenario['groupId'];

    notificationTestClear($this, $adminToken);

    foreach ($scenario['members'] as $token) {
        notificationTestClear($this, $token);
    }

    $activity = activityTestCreate($this, $adminToken, $groupId);

    $memberToken = array_values($scenario['members'])[0];
    $memberList = $this->api->get(
        'app_notifications_list',
        ['category' => 'ACTIVITY', 'status' => 'unread'],
        server: groupTestServer($memberToken),
    );

    $items = authTestBody($memberList)['data'];
    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('ACTIVITY_CREATED')
        ->and($items[0]['data']['activityId'])->toBe($activity['id']);

    $adminList = $this->api->get(
        'app_notifications_list',
        ['category' => 'ACTIVITY', 'status' => 'unread'],
        server: groupTestServer($adminToken),
    );

    expect(authTestBody($adminList)['data'])->toBe([]);
});
