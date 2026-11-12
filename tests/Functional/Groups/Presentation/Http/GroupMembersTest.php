<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

it('lists the approved members for a participant', function () {
    $context = activityTestGroupWithMembers($this, 'group-members');
    $token = $context['members']['group-members-member1@example.com'];
    $adminId = $context['userIds']['group-members-admin@example.com'];

    $response = $this->api->get(
        'app_group_members',
        [],
        ['id' => $context['groupId']],
        groupTestServer($token),
    );

    $members = authTestBody($response)['data'];

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($members)->toHaveCount(3)
        ->and(array_column($members, 'userId'))->toContain($adminId)
        ->and($members[0]['isOwner'])->toBeTrue()
        ->and($members[0]['userId'])->toBe($adminId)
        ->and($members[0]['role'])->toBe('admin')
        ->and($members[1]['membershipId'])->not->toBeEmpty();
});

it('rejects the list for users outside the group', function () {
    $context = activityTestGroupWithMembers($this, 'group-members-outside');
    groupTestCreateUser($this, 'group-members-outsider@example.com');
    $token = groupTestAccessToken($this, 'group-members-outsider@example.com');

    $response = $this->api->get(
        'app_group_members',
        [],
        ['id' => $context['groupId']],
        groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and(authTestBody($response)['errors'][0]['code'])->toBe('GROUP_MEMBERS_ONLY');
});

it('rejects the list for a pending request', function () {
    $context = activityTestGroupWithMembers($this, 'group-members-pending');
    groupTestCreateUser($this, 'group-members-pending-user@example.com');
    $token = groupTestAccessToken($this, 'group-members-pending-user@example.com');
    groupTestRequestMembership($this, $token, $context['groupId']);

    $response = $this->api->get(
        'app_group_members',
        [],
        ['id' => $context['groupId']],
        groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and(authTestBody($response)['errors'][0]['code'])->toBe('GROUP_MEMBERS_ONLY');
});
