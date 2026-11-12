<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

/**
 * @param list<array<string, mixed>> $items
 *
 * @return array<string, mixed>|null
 */
function myRequestsTestFind(array $items, string $membershipId): ?array
{
    foreach ($items as $item) {
        if ($item['id'] === $membershipId) {
            return $item;
        }
    }

    return null;
}

it('lists my pending request with the group name', function () {
    $context = activityTestGroupWithMembers($this, 'my-requests');
    groupTestCreateUser($this, 'my-requests-applicant@example.com');
    $token = groupTestAccessToken($this, 'my-requests-applicant@example.com');
    $membershipId = groupTestRequestMembership($this, $token, $context['groupId']);

    $response = $this->api->get('app_me_membership_requests', server: groupTestServer($token));

    $request = myRequestsTestFind(authTestBody($response)['data'], $membershipId);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($request)->not->toBeNull()
        ->and($request['groupId'])->toBe($context['groupId'])
        ->and($request['groupName'])->toBe('Corrida no Parque')
        ->and($request['groupActive'])->toBeTrue()
        ->and($request['status'])->toBe('pending')
        ->and($request['decidedAt'])->toBeNull()
        ->and($request['decidedByFirstName'])->toBeNull()
        ->and($request['withdrawn'])->toBeFalse();
});

it('reports who approved the request', function () {
    $context = activityTestGroupWithMembers($this, 'my-requests-approved');
    groupTestCreateUser($this, 'my-requests-approved-applicant@example.com');
    $token = groupTestAccessToken($this, 'my-requests-approved-applicant@example.com');
    $membershipId = groupTestRequestMembership($this, $token, $context['groupId']);

    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $context['groupId'], 'membershipId' => $membershipId],
        groupTestServer($context['adminToken']),
    );

    $response = $this->api->get('app_me_membership_requests', server: groupTestServer($token));
    $request = myRequestsTestFind(authTestBody($response)['data'], $membershipId);

    expect($request)->not->toBeNull()
        ->and($request['status'])->toBe('approved')
        ->and($request['decidedAt'])->not->toBeNull()
        ->and($request['decidedByFirstName'])->toBe('Auth');
});

it('marks my cancelled request as withdrawn', function () {
    $context = activityTestGroupWithMembers($this, 'my-requests-withdrawn');
    groupTestCreateUser($this, 'my-requests-withdrawn-applicant@example.com');
    $token = groupTestAccessToken($this, 'my-requests-withdrawn-applicant@example.com');
    $membershipId = groupTestRequestMembership($this, $token, $context['groupId']);

    $this->api->delete(
        'app_group_membership_leave',
        [],
        ['id' => $context['groupId']],
        groupTestServer($token),
    );

    $response = $this->api->get('app_me_membership_requests', server: groupTestServer($token));
    $request = myRequestsTestFind(authTestBody($response)['data'], $membershipId);

    expect($request)->not->toBeNull()
        ->and($request['status'])->toBe('removed')
        ->and($request['withdrawn'])->toBeTrue();
});
