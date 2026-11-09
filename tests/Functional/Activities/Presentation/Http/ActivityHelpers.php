<?php

declare(strict_types=1);

use Tests\FunctionalTestCase;

/**
 * @return array{
 *     adminToken: string,
 *     groupId: string,
 *     members: array<string, string>,
 *     memberships: array<string, string>,
 *     userIds: array<string, string>
 * }
 */
function activityTestGroupWithMembers(
    FunctionalTestCase $test,
    string $prefix = 'activities',
): array {
    $admin = groupTestCreateUser($test, $prefix.'-admin@example.com');
    $adminToken = groupTestAccessToken($test, $prefix.'-admin@example.com');
    $group = groupTestCreate($test, $adminToken);

    $members = [];
    $memberships = [];
    $userIds = [
        $prefix.'-admin@example.com' => $admin->id()->value(),
    ];

    foreach ([1, 2] as $index) {
        $email = $prefix.'-member'.$index.'@example.com';
        $userIds[$email] = groupTestCreateUser($test, $email)->id()->value();
        $token = groupTestAccessToken($test, $email);
        $membershipId = groupTestRequestMembership($test, $token, (string) $group['id']);

        $test->api->post(
            'app_group_membership_approve',
            [],
            ['id' => $group['id'], 'membershipId' => $membershipId],
            groupTestServer($adminToken),
        );

        $members[$email] = $token;
        $memberships[$email] = $membershipId;
    }

    return [
        'adminToken' => $adminToken,
        'groupId' => (string) $group['id'],
        'members' => $members,
        'memberships' => $memberships,
        'userIds' => $userIds,
    ];
}

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function activityTestPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Corrida no Parque',
        'description' => 'Encontro de sábado pela manhã.',
        'startsAt' => (new DateTimeImmutable('+2 hours'))->format(DateTimeInterface::RFC3339_EXTENDED),
        'estimatedDurationMinutes' => 60,
        'meetingPoint' => 'Portão principal do parque',
        'route' => 'Volta completa na pista externa',
        'estimatedDistanceMeters' => 5000,
    ], $overrides);
}

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function activityTestCreate(
    FunctionalTestCase $test,
    string $accessToken,
    string $groupId,
    array $overrides = [],
): array {
    $response = $test->api->post(
        'app_activity_create',
        activityTestPayload($overrides),
        ['groupId' => $groupId],
        groupTestServer($accessToken),
    );

    return authTestBody($response)['data'];
}

function activityTestEnroll(
    FunctionalTestCase $test,
    string $accessToken,
    string $activityId,
): \Symfony\Component\HttpFoundation\Response {
    return $test->api->post(
        'app_activity_enroll',
        [],
        ['id' => $activityId],
        groupTestServer($accessToken),
    );
}
