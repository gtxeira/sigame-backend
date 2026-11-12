<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

/**
 * @return array{adminToken: string, groupId: string, adminMembershipId: string}
 */
function groupTestAdminContext(\Tests\FunctionalTestCase $test, string $email = 'groups-membership-admin@example.com'): array
{
    groupTestCreateUser($test, $email);
    $adminToken = groupTestAccessToken($test, $email);
    $group = groupTestCreate($test, $adminToken);

    return [
        'adminToken' => $adminToken,
        'groupId' => (string) $group['id'],
        'adminMembershipId' => (string) $group['viewerMembership']['id'],
    ];
}

it('creates a pending membership request and rejects duplicates', function () {
    $context = groupTestAdminContext($this);

    groupTestCreateUser($this, 'groups-membership-member@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-membership-member@example.com');

    $response = $this->api->post(
        'app_group_membership_request',
        [],
        ['id' => $context['groupId']],
        groupTestServer($memberToken),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED)
        ->and(authTestBody($response)['data']['status'])->toBe('pending')
        ->and(authTestBody($response)['data']['role'])->toBe('member');

    $duplicate = $this->api->post(
        'app_group_membership_request',
        [],
        ['id' => $context['groupId']],
        groupTestServer($memberToken),
    );

    expect($duplicate->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_MEMBERSHIP_ALREADY_EXISTS']);
});

it('approves members and activates the group at three approved members', function () {
    $context = groupTestAdminContext($this);

    groupTestCreateUser($this, 'groups-approve-1@example.com');
    $firstToken = groupTestAccessToken($this, 'groups-approve-1@example.com');
    $firstMembership = groupTestRequestMembership($this, $firstToken, $context['groupId']);

    groupTestCreateUser($this, 'groups-approve-2@example.com');
    $secondToken = groupTestAccessToken($this, 'groups-approve-2@example.com');
    $secondMembership = groupTestRequestMembership($this, $secondToken, $context['groupId']);

    foreach ([$firstMembership, $secondMembership] as $membershipId) {
        $response = $this->api->post(
            'app_group_membership_approve',
            [],
            ['id' => $context['groupId'], 'membershipId' => $membershipId],
            groupTestServer($context['adminToken']),
        );

        expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    }

    $group = authTestBody($this->api->get(
        'app_group_show',
        [],
        ['id' => $context['groupId']],
        groupTestServer($context['adminToken']),
    ))['data'];

    expect($group['approvedMembers'])->toBe(3)
        ->and($group['status'])->toBe('active');
});

it('rejects approval when the configured member limit is reached', function () {
    $context = groupTestAdminContext($this);

    $this->api->patch(
        'app_group_update',
        ['maxMembers' => 3],
        ['id' => $context['groupId']],
        groupTestServer($context['adminToken']),
    );

    foreach (['groups-limit-1@example.com', 'groups-limit-2@example.com'] as $email) {
        groupTestCreateUser($this, $email);
        $memberToken = groupTestAccessToken($this, $email);
        $membershipId = groupTestRequestMembership($this, $memberToken, $context['groupId']);

        $response = $this->api->post(
            'app_group_membership_approve',
            [],
            ['id' => $context['groupId'], 'membershipId' => $membershipId],
            groupTestServer($context['adminToken']),
        );

        expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    }

    groupTestCreateUser($this, 'groups-limit-3@example.com');
    $extraToken = groupTestAccessToken($this, 'groups-limit-3@example.com');
    $extraMembership = groupTestRequestMembership($this, $extraToken, $context['groupId']);

    $response = $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $context['groupId'], 'membershipId' => $extraMembership],
        groupTestServer($context['adminToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_FULL']);
});

it('rejects approval by a non admin', function () {
    $context = groupTestAdminContext($this);

    groupTestCreateUser($this, 'groups-non-admin-1@example.com');
    $firstToken = groupTestAccessToken($this, 'groups-non-admin-1@example.com');
    $firstMembership = groupTestRequestMembership($this, $firstToken, $context['groupId']);

    groupTestCreateUser($this, 'groups-non-admin-2@example.com');
    $secondToken = groupTestAccessToken($this, 'groups-non-admin-2@example.com');
    $secondMembership = groupTestRequestMembership($this, $secondToken, $context['groupId']);

    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $context['groupId'], 'membershipId' => $firstMembership],
        groupTestServer($context['adminToken']),
    );

    $response = $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $context['groupId'], 'membershipId' => $secondMembership],
        groupTestServer($firstToken),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['GROUP_ADMIN_REQUIRED']);
});

it('removes a member with a reason', function () {
    $context = groupTestAdminContext($this);

    groupTestCreateUser($this, 'groups-remove-member@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-remove-member@example.com');
    $membershipId = groupTestRequestMembership($this, $memberToken, $context['groupId']);

    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $context['groupId'], 'membershipId' => $membershipId],
        groupTestServer($context['adminToken']),
    );

    $response = $this->api->post(
        'app_group_membership_remove',
        ['reason' => 'Descumprimento das regras.'],
        ['id' => $context['groupId'], 'membershipId' => $membershipId],
        groupTestServer($context['adminToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($response)['data'];

    expect($data['status'])->toBe('removed')
        ->and($data['removalReason'])->toBe('Descumprimento das regras.')
        ->and($data['removedAt'])->not->toBeNull();
});

it('promotes and demotes members but never demotes the owner', function () {
    $context = groupTestAdminContext($this);

    groupTestCreateUser($this, 'groups-role-member@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-role-member@example.com');
    $membershipId = groupTestRequestMembership($this, $memberToken, $context['groupId']);

    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $context['groupId'], 'membershipId' => $membershipId],
        groupTestServer($context['adminToken']),
    );

    $promotion = $this->api->patch(
        'app_group_membership_role',
        ['role' => 'admin'],
        ['id' => $context['groupId'], 'membershipId' => $membershipId],
        groupTestServer($context['adminToken']),
    );

    expect($promotion->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($promotion)['data']['role'])->toBe('admin');

    $demotion = $this->api->patch(
        'app_group_membership_role',
        ['role' => 'member'],
        ['id' => $context['groupId'], 'membershipId' => $membershipId],
        groupTestServer($context['adminToken']),
    );

    expect($demotion->getStatusCode())->toBe(Response::HTTP_OK);

    $ownerDemotion = $this->api->patch(
        'app_group_membership_role',
        ['role' => 'member'],
        ['id' => $context['groupId'], 'membershipId' => $context['adminMembershipId']],
        groupTestServer($context['adminToken']),
    );

    expect($ownerDemotion->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_OWNER_TRANSFER_REQUIRED']);
});

it('designates and clears a supervisor', function () {
    $context = groupTestAdminContext($this);

    groupTestCreateUser($this, 'groups-supervisor@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-supervisor@example.com');
    $membershipId = groupTestRequestMembership($this, $memberToken, $context['groupId']);

    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $context['groupId'], 'membershipId' => $membershipId],
        groupTestServer($context['adminToken']),
    );

    $designation = $this->api->put(
        'app_group_supervisor_designate',
        ['membershipId' => $membershipId],
        ['id' => $context['groupId']],
        groupTestServer($context['adminToken']),
    );

    expect($designation->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($designation)['data']['isSupervisor'])->toBeTrue();

    $clear = $this->api->delete(
        'app_group_supervisor_clear',
        [],
        ['id' => $context['groupId']],
        groupTestServer($context['adminToken']),
    );

    expect($clear->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($clear)['data']['removed'])->toBeTrue();
});

it('lists group memberships only for admins', function () {
    $context = groupTestAdminContext($this);

    groupTestCreateUser($this, 'groups-list-members@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-list-members@example.com');
    groupTestRequestMembership($this, $memberToken, $context['groupId']);

    $adminResponse = $this->api->get(
        'app_group_membership_list',
        [],
        ['id' => $context['groupId']],
        groupTestServer($context['adminToken']),
    );

    expect($adminResponse->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($adminResponse)['data'])->toHaveCount(2);

    $memberResponse = $this->api->get(
        'app_group_membership_list',
        [],
        ['id' => $context['groupId']],
        groupTestServer($memberToken),
    );

    expect($memberResponse->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['GROUP_ADMIN_REQUIRED']);
});
