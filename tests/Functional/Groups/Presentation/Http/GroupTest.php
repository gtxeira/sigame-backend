<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Modality;
use Symfony\Component\HttpFoundation\Response;

it('rejects group creation for a level 1 user', function () {
    groupTestCreateUser($this, 'groups-level1@example.com', level: 1);
    $token = groupTestAccessToken($this, 'groups-level1@example.com');

    $response = $this->api->post('app_group_create', groupTestPayload(), server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['GROUP_LEVEL_INSUFFICIENT']);
});

it('creates a group with the authenticated user as admin', function () {
    $user = groupTestCreateUser($this, 'groups-create@example.com');
    $token = groupTestAccessToken($this, 'groups-create@example.com');

    $response = $this->api->post('app_group_create', groupTestPayload(), server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    $data = authTestBody($response)['data'];

    expect($data['name'])->toBe('Corrida no Parque')
        ->and($data['modality'])->toBe(Modality::RUNNING->value)
        ->and($data['status'])->toBe('forming')
        ->and($data['approvedMembers'])->toBe(1)
        ->and($data['maxMembers'])->toBe(5)
        ->and($data['createdBy'])->toBe($user->id()->value())
        ->and($data['communicationLink'])->toBe('https://chat.example.com/grupo/corrida')
        ->and($data['viewerMembership']['role'])->toBe('admin')
        ->and($data['viewerMembership']['status'])->toBe('approved');
});

it('validates group creation payload', function () {
    groupTestCreateUser($this, 'groups-invalid@example.com');
    $token = groupTestAccessToken($this, 'groups-invalid@example.com');

    $response = $this->api->post(
        'app_group_create',
        ['name' => 'ab', 'modality' => 'natação'],
        server: groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);
});

it('lists and filters groups', function () {
    groupTestCreateUser($this, 'groups-list@example.com');
    $token = groupTestAccessToken($this, 'groups-list@example.com');

    groupTestCreate($this, $token, ['name' => 'Corrida no Parque', 'modality' => Modality::RUNNING->value]);
    groupTestCreate($this, $token, ['name' => 'Pedal da Serra', 'modality' => Modality::CYCLING->value]);

    $response = $this->api->get(
        'app_group_list',
        ['modality' => Modality::CYCLING->value],
        server: groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $body = authTestBody($response);

    expect($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['name'])->toBe('Pedal da Serra')
        ->and($body['meta']['pagination']['total'])->toBe(1);
});

it('filters groups by status and availability', function () {
    groupTestCreateUser($this, 'groups-status@example.com');
    $token = groupTestAccessToken($this, 'groups-status@example.com');
    groupTestCreate($this, $token);

    $forming = $this->api->get(
        'app_group_list',
        ['status' => 'forming', 'availableOnly' => true],
        server: groupTestServer($token),
    );

    expect($forming->getStatusCode())->toBe(Response::HTTP_OK);

    $formingBody = authTestBody($forming);

    expect($formingBody['data'])->toHaveCount(1)
        ->and($formingBody['meta']['pagination']['total'])->toBe(1);

    $active = $this->api->get(
        'app_group_list',
        ['status' => 'active'],
        server: groupTestServer($token),
    );

    expect($active->getStatusCode())->toBe(Response::HTTP_OK);

    $activeBody = authTestBody($active);

    expect($activeBody)->toHaveKey('data')
        ->and($activeBody['data'])->toBe([])
        ->and($activeBody['meta']['pagination']['total'])->toBe(0);
});

it('returns an empty list of my groups', function () {
    groupTestCreateUser($this, 'groups-mine-empty@example.com');
    $token = groupTestAccessToken($this, 'groups-mine-empty@example.com');

    $response = $this->api->get('app_group_mine', server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $body = authTestBody($response);

    expect($body)->toHaveKey('data')
        ->and($body['data'])->toBe([]);
});

it('shows the communication link only to approved members', function () {
    $admin = groupTestCreateUser($this, 'groups-link-admin@example.com');
    $adminToken = groupTestAccessToken($this, 'groups-link-admin@example.com');
    $group = groupTestCreate($this, $adminToken);

    groupTestCreateUser($this, 'groups-link-member@example.com');
    $memberToken = groupTestAccessToken($this, 'groups-link-member@example.com');
    $membershipId = groupTestRequestMembership($this, $memberToken, $group['id']);

    $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $group['id'], 'membershipId' => $membershipId],
        groupTestServer($adminToken),
    );

    groupTestCreateUser($this, 'groups-link-outsider@example.com');
    $outsiderToken = groupTestAccessToken($this, 'groups-link-outsider@example.com');

    $adminView = authTestBody($this->api->get('app_group_show', [], ['id' => $group['id']], groupTestServer($adminToken)))['data'];
    $memberView = authTestBody($this->api->get('app_group_show', [], ['id' => $group['id']], groupTestServer($memberToken)))['data'];
    $outsiderView = authTestBody($this->api->get('app_group_show', [], ['id' => $group['id']], groupTestServer($outsiderToken)))['data'];

    expect($adminView['communicationLink'])->toBe('https://chat.example.com/grupo/corrida')
        ->and($memberView['communicationLink'])->toBe('https://chat.example.com/grupo/corrida')
        ->and($outsiderView['communicationLink'])->toBeNull()
        ->and($outsiderView['viewerMembership'])->toBeNull()
        ->and($admin->id()->value())->toBe($group['createdBy']);
});

it('updates a group only for admins', function () {
    groupTestCreateUser($this, 'groups-update-admin@example.com');
    $adminToken = groupTestAccessToken($this, 'groups-update-admin@example.com');
    $group = groupTestCreate($this, $adminToken);

    groupTestCreateUser($this, 'groups-update-outsider@example.com');
    $outsiderToken = groupTestAccessToken($this, 'groups-update-outsider@example.com');

    $forbidden = $this->api->patch(
        'app_group_update',
        ['name' => 'Novo nome'],
        ['id' => $group['id']],
        groupTestServer($outsiderToken),
    );

    expect($forbidden->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['GROUP_ADMIN_REQUIRED']);

    $updated = $this->api->patch(
        'app_group_update',
        ['name' => 'Corrida no Parque Norte', 'communicationLink' => null],
        ['id' => $group['id']],
        groupTestServer($adminToken),
    );

    expect($updated->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($updated)['data'];

    expect($data['name'])->toBe('Corrida no Parque Norte')
        ->and($data['communicationLink'])->toBeNull();
});

it('rate limits repeated group creation attempts', function () {
    groupTestCreateUser($this, 'groups-rate@example.com', level: 1);
    $token = groupTestAccessToken($this, 'groups-rate@example.com');

    for ($attempt = 1; $attempt <= 5; ++$attempt) {
        $response = $this->api->post('app_group_create', groupTestPayload(), server: groupTestServer($token));
        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    }

    $response = $this->api->post('app_group_create', groupTestPayload(), server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});
