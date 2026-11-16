<?php

declare(strict_types=1);

namespace Tests\Functional\Administration\Presentation\Http;

it('records account actions in the audit trail', function () {
    authTestCreateAdmin($this, 'admin-audit@example.com');
    $target = authTestCreateUser($this, 'target-audit@example.com');
    $token = groupTestAccessToken($this, 'admin-audit@example.com');

    $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Descumprimento das regras.'],
        ['id' => $target->id()->value()],
        groupTestServer($token),
    );

    $this->api->post(
        'app_admin_user_reactivate',
        ['reason' => 'Revisão concluída.'],
        ['id' => $target->id()->value()],
        groupTestServer($token),
    );

    $response = $this->api->get('app_admin_audit_list', server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(200);

    $body = authTestBody($response);
    expect($body['meta']['pagination']['total'])->toBe(2)
        ->and($body['data'])->toHaveCount(2)
        ->and($body['data'][0]['action'])->toBe('ACCOUNT_REACTIVATED')
        ->and($body['data'][1]['action'])->toBe('ACCOUNT_SUSPENDED')
        ->and($body['data'][1]['targetType'])->toBe('USER')
        ->and($body['data'][1]['targetId'])->toBe($target->id()->value())
        ->and($body['data'][1]['targetLabel'])->toBe('Auth Test User')
        ->and($body['data'][1]['actorName'])->toBe('Auth Test User')
        ->and($body['data'][1]['reason'])->toBe('Descumprimento das regras.');
});

it('filters the audit trail by action and target type', function () {
    authTestCreateAdmin($this, 'admin-audit-filter@example.com');
    $target = authTestCreateUser($this, 'target-audit-filter@example.com');
    $token = groupTestAccessToken($this, 'admin-audit-filter@example.com');

    $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Motivo válido.'],
        ['id' => $target->id()->value()],
        groupTestServer($token),
    );

    $matching = $this->api->get(
        'app_admin_audit_list',
        ['action' => 'ACCOUNT_SUSPENDED', 'targetType' => 'USER'],
        server: groupTestServer($token),
    );
    expect(authTestBody($matching)['meta']['pagination']['total'])->toBe(1);

    $empty = $this->api->get(
        'app_admin_audit_list',
        ['action' => 'GROUP_DELETED'],
        server: groupTestServer($token),
    );
    expect(authTestBody($empty)['meta']['pagination']['total'])->toBe(0);
});

it('does not allow a regular user to read the audit trail', function () {
    authTestCreateUser($this, 'user-audit-denied@example.com');
    $token = groupTestAccessToken($this, 'user-audit-denied@example.com');

    $response = $this->api->get('app_admin_audit_list', server: groupTestServer($token));

    expect($response->getStatusCode())->toBe(403);
});
