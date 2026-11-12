<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * @return array<string, mixed>
 */
function groupListTestCreateActivity(
    FunctionalTestCase $test,
    string $accessToken,
    string $groupId,
    DateTimeImmutable $startsAt,
): array {
    $response = $test->api->post('app_activity_create', [
        'title' => 'Treino de teste',
        'startsAt' => groupListTestIso($startsAt),
        'meetingPoint' => 'Portão principal do parque',
        'route' => 'Volta completa na pista externa',
        'estimatedDistanceMeters' => 3000,
    ], ['groupId' => $groupId], groupTestServer($accessToken));

    return authTestBody($response)['data'];
}

/** Formata em UTC com `Z` para o `+` do offset não virar espaço na query string. */
function groupListTestIso(DateTimeImmutable $date): string
{
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}

/**
 * @return array<string, string>
 */
function groupListTestRange(string $from, string $to): array
{
    return ['activityFrom' => $from, 'activityTo' => $to];
}

it('filters groups by the activity range and exposes the next activity', function () {
    $context = activityTestGroupWithMembers($this, 'list-activities');
    $activity = groupListTestCreateActivity(
        $this,
        $context['adminToken'],
        $context['groupId'],
        new DateTimeImmutable('+2 hours'),
    );

    $range = groupListTestRange(
        groupListTestIso(new DateTimeImmutable('-1 hour')),
        groupListTestIso(new DateTimeImmutable('+1 day')),
    );

    $response = $this->api->get(
        'app_group_list',
        [...$range, 'perPage' => 30],
        server: groupTestServer($context['adminToken']),
    );

    $items = array_values(array_filter(
        authTestBody($response)['data'],
        static fn (array $group): bool => $group['id'] === $context['groupId'],
    ));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($items)->toHaveCount(1)
        ->and($items[0]['nextActivity'])->not->toBeNull()
        ->and($items[0]['nextActivity']['activityId'])->toBe($activity['id'])
        ->and($items[0]['nextActivity']['title'])->toBe('Treino de teste')
        ->and($items[0]['nextActivity']['status'])->toBe('scheduled');
});

it('hides the activity title from users outside the group', function () {
    $context = activityTestGroupWithMembers($this, 'list-activities-outside');
    groupListTestCreateActivity($this, $context['adminToken'], $context['groupId'], new DateTimeImmutable('+2 hours'));

    groupTestCreateUser($this, 'list-activities-outsider@example.com');
    $outsiderToken = groupTestAccessToken($this, 'list-activities-outsider@example.com');

    $response = $this->api->get(
        'app_group_list',
        [
            ...groupListTestRange(
                groupListTestIso(new DateTimeImmutable('-1 hour')),
                groupListTestIso(new DateTimeImmutable('+1 day')),
            ),
            'perPage' => 30,
        ],
        server: groupTestServer($outsiderToken),
    );

    $items = array_values(array_filter(
        authTestBody($response)['data'],
        static fn (array $group): bool => $group['id'] === $context['groupId'],
    ));

    expect($items)->toHaveCount(1)
        ->and($items[0]['nextActivity']['title'])->toBeNull();
});

it('excludes groups without activities in the requested range', function () {
    $context = activityTestGroupWithMembers($this, 'list-activities-future');
    groupListTestCreateActivity($this, $context['adminToken'], $context['groupId'], new DateTimeImmutable('+2 hours'));

    $response = $this->api->get(
        'app_group_list',
        [
            ...groupListTestRange(
                groupListTestIso(new DateTimeImmutable('+10 days')),
                groupListTestIso(new DateTimeImmutable('+11 days')),
            ),
            'perPage' => 30,
        ],
        server: groupTestServer($context['adminToken']),
    );

    $ids = array_column(authTestBody($response)['data'], 'id');

    expect($ids)->not->toContain($context['groupId']);
});

it('rejects an incomplete activity range', function () {
    $context = activityTestGroupWithMembers($this, 'list-activities-broken');

    $response = $this->api->get(
        'app_group_list',
        ['activityFrom' => groupListTestIso(new DateTimeImmutable())],
        server: groupTestServer($context['adminToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and(authTestBody($response)['errors'][0]['code'])->toBe('FIELD_REQUIRED')
        ->and(authTestBody($response)['errors'][0]['field'])->toBe('activityTo');
});

it('rejects an inverted activity range', function () {
    $context = activityTestGroupWithMembers($this, 'list-activities-inverted');

    $response = $this->api->get(
        'app_group_list',
        [
            'activityFrom' => groupListTestIso(new DateTimeImmutable('+2 days')),
            'activityTo' => groupListTestIso(new DateTimeImmutable('+1 day')),
        ],
        server: groupTestServer($context['adminToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and(authTestBody($response)['errors'][0]['code'])->toBe('NUMBER_OUT_OF_RANGE');
});
