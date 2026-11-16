<?php

declare(strict_types=1);

namespace Tests\Functional\Administration\Presentation\Http;

it('returns the consolidated overview for an admin', function () {
    authTestCreateAdmin($this, 'admin-overview@example.com');
    authTestCreateUser($this, 'user-overview@example.com');

    $token = groupTestAccessToken($this, 'admin-overview@example.com');

    $response = $this->api->get('app_admin_overview', server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(200);

    $data = authTestBody($response)['data'];

    expect($data['users'])->toBe([
        'total' => 2,
        'active' => 2,
        'suspended' => 0,
        'admins' => 1,
    ])
        ->and($data['groups'])->toBe(['active' => 0, 'archived' => 0, 'deleted' => 0])
        ->and($data['activities'])->toBe([
            'scheduled' => 0,
            'started' => 0,
            'finished' => 0,
            'cancelled' => 0,
            'invalidated' => 0,
        ])
        ->and($data['attendances'])->toBe(['checkedInLast7Days' => 0]);
});

it('does not allow a regular user to read the overview', function () {
    authTestCreateUser($this, 'user-overview-denied@example.com');
    $token = groupTestAccessToken($this, 'user-overview-denied@example.com');

    $response = $this->api->get('app_admin_overview', server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(403);
});
