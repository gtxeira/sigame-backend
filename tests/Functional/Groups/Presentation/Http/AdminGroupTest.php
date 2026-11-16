<?php

declare(strict_types=1);

namespace Tests\Functional\Groups\Presentation\Http;

it('lists groups for the administration including archived and deleted', function () {
    groupTestCreateUser($this, 'owner-admin-group@example.com');
    $ownerToken = groupTestAccessToken($this, 'owner-admin-group@example.com');
    groupTestCreate($this, $ownerToken, ['name' => 'Grupo Ativo']);

    groupTestCreateUser($this, 'other-admin-group@example.com');
    $otherToken = groupTestAccessToken($this, 'other-admin-group@example.com');
    $archived = groupTestCreate($this, $otherToken, ['name' => 'Grupo Arquivado']);

    authTestCreateAdmin($this, 'admin-groups@example.com');
    $adminToken = groupTestAccessToken($this, 'admin-groups@example.com');

    $this->api->post(
        'app_group_archive',
        ['reason' => 'Pausa administrativa.'],
        ['id' => $archived['id']],
        groupTestServer($adminToken),
    );

    $response = $this->api->get(
        'app_admin_group_list',
        ['visibility' => 'all'],
        server: groupTestServer($adminToken),
    );

    expect($response->getStatusCode())->toBe(200);

    $body = authTestBody($response);
    $names = array_column($body['data'], 'name');

    expect($body['meta']['pagination']['total'])->toBe(2)
        ->and($names)->toContain('Grupo Ativo', 'Grupo Arquivado')
        ->and($body['data'][0]['ownerName'])->toBe('Auth Test User');

    $archivedResponse = $this->api->get(
        'app_admin_group_list',
        ['visibility' => 'archived'],
        server: groupTestServer($adminToken),
    );

    $archivedBody = authTestBody($archivedResponse);
    expect($archivedBody['meta']['pagination']['total'])->toBe(1)
        ->and($archivedBody['data'][0]['name'])->toBe('Grupo Arquivado')
        ->and($archivedBody['data'][0]['archived'])->toBeTrue();
});

it('lets a SIGAME admin archive, unarchive and delete a group without membership', function () {
    groupTestCreateUser($this, 'owner-lifecycle@example.com');
    $ownerToken = groupTestAccessToken($this, 'owner-lifecycle@example.com');
    $group = groupTestCreate($this, $ownerToken, ['name' => 'Grupo Moderado']);

    authTestCreateAdmin($this, 'admin-lifecycle@example.com');
    $adminToken = groupTestAccessToken($this, 'admin-lifecycle@example.com');
    $server = groupTestServer($adminToken);

    $archive = $this->api->post(
        'app_group_archive',
        ['reason' => 'Pausa administrativa.'],
        ['id' => $group['id']],
        $server,
    );
    expect($archive->getStatusCode())->toBe(200)
        ->and(authTestBody($archive)['data']['archived'])->toBeTrue();

    $detail = $this->api->get('app_admin_group_show', [], ['id' => $group['id']], $server);
    expect($detail->getStatusCode())->toBe(200)
        ->and(authTestBody($detail)['data']['archiveReason'])->toBe('Pausa administrativa.')
        ->and(authTestBody($detail)['data']['archivedByName'])->toBe('Auth Test User');

    $unarchive = $this->api->post('app_group_unarchive', [], ['id' => $group['id']], $server);
    expect($unarchive->getStatusCode())->toBe(200)
        ->and(authTestBody($unarchive)['data']['archived'])->toBeFalse();

    $delete = $this->api->delete(
        'app_group_delete',
        ['reason' => 'Encerrado pela administração.'],
        ['id' => $group['id']],
        $server,
    );
    expect($delete->getStatusCode())->toBe(200)
        ->and(authTestBody($delete)['data']['deleted'])->toBeTrue();

    $deletedDetail = $this->api->get('app_admin_group_show', [], ['id' => $group['id']], $server);
    expect(authTestBody($deletedDetail)['data']['deleted'])->toBeTrue()
        ->and(authTestBody($deletedDetail)['data']['deleteReason'])->toBe('Encerrado pela administração.');

    $audit = $this->api->get('app_admin_audit_list', ['targetType' => 'GROUP'], server: $server);
    $actions = array_column(authTestBody($audit)['data'], 'action');
    expect($actions)->toContain('GROUP_ARCHIVED', 'GROUP_UNARCHIVED', 'GROUP_DELETED');
});

it('keeps group actions restricted for regular users', function () {
    groupTestCreateUser($this, 'owner-restricted@example.com');
    $ownerToken = groupTestAccessToken($this, 'owner-restricted@example.com');
    $group = groupTestCreate($this, $ownerToken, ['name' => 'Grupo Restrito']);

    groupTestCreateUser($this, 'outsider-restricted@example.com');
    $outsiderToken = groupTestAccessToken($this, 'outsider-restricted@example.com');

    $list = $this->api->get('app_admin_group_list', server: groupTestServer($outsiderToken));
    expect($list->getStatusCode())->toBe(403);

    $archive = $this->api->post(
        'app_group_archive',
        ['reason' => 'Sem vínculo.'],
        ['id' => $group['id']],
        groupTestServer($outsiderToken),
    );
    expect($archive->getStatusCode())->toBe(403)
        ->and($this->api->errorCodes())->toContain('GROUP_ADMIN_REQUIRED');

    $delete = $this->api->delete(
        'app_group_delete',
        [],
        ['id' => $group['id']],
        groupTestServer($outsiderToken),
    );
    expect($delete->getStatusCode())->toBe(403)
        ->and($this->api->errorCodes())->toContain('GROUP_OWNER_REQUIRED');
});

it('lists every membership status for the administration', function () {
    groupTestCreateUser($this, 'owner-members@example.com');
    $ownerToken = groupTestAccessToken($this, 'owner-members@example.com');
    $group = groupTestCreate($this, $ownerToken, ['name' => 'Grupo com Membros']);

    groupTestCreateUser($this, 'pending-member@example.com');
    $pendingToken = groupTestAccessToken($this, 'pending-member@example.com');
    groupTestRequestMembership($this, $pendingToken, $group['id']);

    authTestCreateAdmin($this, 'admin-members@example.com');
    $adminToken = groupTestAccessToken($this, 'admin-members@example.com');
    $server = groupTestServer($adminToken);

    $all = $this->api->get('app_admin_group_members', [], ['id' => $group['id']], $server);
    expect($all->getStatusCode())->toBe(200)
        ->and(authTestBody($all)['data'])->toHaveCount(2);

    $pending = $this->api->get(
        'app_admin_group_members',
        ['status' => 'pending'],
        ['id' => $group['id']],
        $server,
    );
    $pendingBody = authTestBody($pending);
    expect($pendingBody['data'])->toHaveCount(1)
        ->and($pendingBody['data'][0]['status'])->toBe('pending')
        ->and($pendingBody['data'][0]['user']['firstName'])->not->toBeEmpty();

    groupTestCreateUser($this, 'regular-members@example.com');
    $regular = groupTestAccessToken($this, 'regular-members@example.com');
    $denied = $this->api->get(
        'app_admin_group_members',
        [],
        ['id' => $group['id']],
        groupTestServer($regular),
    );
    expect($denied->getStatusCode())->toBe(403);
});
