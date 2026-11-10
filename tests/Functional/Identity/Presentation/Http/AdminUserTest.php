<?php

declare(strict_types=1);

use App\Identity\Domain\User\Entity\User;

it('lets an admin suspend and reactivate a user and records the history', function () {
    $admin = authTestCreateAdmin($this, 'admin-suspend@example.com');
    $target = authTestCreateUser($this, 'target-suspend@example.com');
    $adminToken = authTestAccessToken(authTestLogin($this, 'admin-suspend@example.com'));

    $suspend = $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Descumprimento das regras de convivência.'],
        ['id' => $target->id()->value()],
        groupTestServer($adminToken),
    );

    expect($suspend->getStatusCode())->toBe(200);
    $body = authTestBody($suspend);
    expect($body['data']['changed'])->toBeTrue()
        ->and($body['data']['user']['status'])->toBe('SUSPENDED')
        ->and($body['data']['history']['action'])->toBe('SUSPENDED')
        ->and($body['data']['history']['reason'])->toBe('Descumprimento das regras de convivência.');

    $login = authTestLogin($this, 'target-suspend@example.com');
    expect($login->getStatusCode())->toBe(403)
        ->and($this->api->errorCodes())->toContain('ACCOUNT_SUSPENDED');

    $reactivate = $this->api->post(
        'app_admin_user_reactivate',
        ['reason' => 'Revisão concluída.'],
        ['id' => $target->id()->value()],
        groupTestServer($adminToken),
    );

    expect($reactivate->getStatusCode())->toBe(200);
    $body = authTestBody($reactivate);
    expect($body['data']['changed'])->toBeTrue()
        ->and($body['data']['user']['status'])->toBe('ACTIVE')
        ->and($body['data']['history']['action'])->toBe('REACTIVATED');

    expect(authTestLogin($this, 'target-suspend@example.com')->getStatusCode())->toBe(200);

    $history = $this->api->get(
        'app_admin_user_status_history',
        [],
        ['id' => $target->id()->value()],
        groupTestServer($adminToken),
    );

    expect($history->getStatusCode())->toBe(200);
    $items = authTestBody($history)['data'];
    expect($items)->toHaveCount(2)
        ->and($items[0]['action'])->toBe('REACTIVATED')
        ->and($items[1]['action'])->toBe('SUSPENDED');

    expect($admin->role()->value)->toBe('ADMIN_SIGAME');
});

it('revokes every session when an account is suspended', function () {
    authTestCreateAdmin($this, 'admin-revoke@example.com');
    $target = authTestCreateUser($this, 'target-revoke@example.com');

    $login = authTestLogin($this, 'target-revoke@example.com');
    $refreshCookie = authTestRefreshCookie($login);
    expect($refreshCookie)->not->toBeNull();

    $adminToken = authTestAccessToken(authTestLogin($this, 'admin-revoke@example.com'));
    $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Fraude confirmada.'],
        ['id' => $target->id()->value()],
        groupTestServer($adminToken),
    );

    authTestSetRefreshCookie($this, (string) $refreshCookie);
    $refresh = $this->api->post('app_auth_refresh');

    expect($refresh->getStatusCode())->toBe(401);
    $codes = $this->api->errorCodes();
    expect($codes)->not->toBeEmpty()
        ->and($codes[0])->toStartWith('REFRESH_TOKEN');
});

it('is idempotent when suspending or reactivating an account', function () {
    authTestCreateAdmin($this, 'admin-idempotent@example.com');
    $target = authTestCreateUser($this, 'target-idempotent@example.com');
    $adminToken = authTestAccessToken(authTestLogin($this, 'admin-idempotent@example.com'));

    $first = $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Motivo válido.'],
        ['id' => $target->id()->value()],
        groupTestServer($adminToken),
    );
    $second = $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Outro motivo válido.'],
        ['id' => $target->id()->value()],
        groupTestServer($adminToken),
    );

    expect(authTestBody($first)['data']['changed'])->toBeTrue()
        ->and($second->getStatusCode())->toBe(200)
        ->and(authTestBody($second)['data']['changed'])->toBeFalse()
        ->and(authTestBody($second)['data']['history'])->toBeNull();
});

it('does not allow a regular user to manage accounts', function () {
    authTestCreateUser($this, 'regular-admin-check@example.com');
    $target = authTestCreateUser($this, 'target-regular-check@example.com');
    $token = authTestAccessToken(authTestLogin($this, 'regular-admin-check@example.com'));

    $response = $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Sem permissão.'],
        ['id' => $target->id()->value()],
        groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(403);
});

it('does not allow suspending an admin account', function () {
    authTestCreateAdmin($this, 'admin-one@example.com');
    $anotherAdmin = authTestCreateAdmin($this, 'admin-two@example.com');
    $token = authTestAccessToken(authTestLogin($this, 'admin-one@example.com'));

    $response = $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Tentativa indevida.'],
        ['id' => $anotherAdmin->id()->value()],
        groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(403)
        ->and($this->api->errorCodes())->toContain('CANNOT_SUSPEND_ADMIN');
});

it('validates the suspension reason', function () {
    authTestCreateAdmin($this, 'admin-validation@example.com');
    $target = authTestCreateUser($this, 'target-validation@example.com');
    $token = authTestAccessToken(authTestLogin($this, 'admin-validation@example.com'));

    $response = $this->api->post(
        'app_admin_user_suspend',
        ['reason' => ''],
        ['id' => $target->id()->value()],
        groupTestServer($token),
    );

    expect($response->getStatusCode())->toBe(422);
});

it('lists and searches accounts without exposing sensitive profile data', function () {
    authTestCreateAdmin($this, 'admin-list@example.com');
    authTestCreateUser($this, 'ana-list@example.com');
    authTestCreateUser($this, 'bruno-list@example.com');

    $token = authTestAccessToken(authTestLogin($this, 'admin-list@example.com'));

    $list = $this->api->get(
        'app_admin_user_list',
        ['query' => 'ana-list', 'page' => 1, 'perPage' => 20],
        server: groupTestServer($token),
    );

    expect($list->getStatusCode())->toBe(200);
    $body = authTestBody($list);
    expect($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['email'])->toBe('ana-list@example.com')
        ->and($body['data'][0])->not->toHaveKeys(['cpf', 'heightCm', 'weightKg', 'phone'])
        ->and($body['meta']['pagination']['total'])->toBe(1);

    $detail = $this->api->get(
        'app_admin_user_show',
        [],
        ['id' => $body['data'][0]['id']],
        groupTestServer($token),
    );

    expect($detail->getStatusCode())->toBe(200)
        ->and(authTestBody($detail)['data']['email'])->toBe('ana-list@example.com');
});

it('rejects approval of a pending membership when the requester is suspended', function () {
    groupTestCreateUser($this, 'admin-membership-suspended@example.com');
    $adminToken = groupTestAccessToken($this, 'admin-membership-suspended@example.com');
    $group = groupTestCreate($this, $adminToken);

    groupTestCreateUser($this, 'requester-suspended@example.com');
    $requesterToken = groupTestAccessToken($this, 'requester-suspended@example.com');
    $membershipId = groupTestRequestMembership($this, $requesterToken, $group['id']);

    $requester = authTestUserRepository($this)->findByEmail(
        \App\Shared\Domain\ValueObject\Email::fromString('requester-suspended@example.com')->value(),
    );
    assert($requester instanceof User);
    $requester->suspend();
    authTestUserRepository($this)->save($requester);

    $response = $this->api->post(
        'app_group_membership_approve',
        [],
        ['id' => $group['id'], 'membershipId' => $membershipId],
        groupTestServer($adminToken),
    );

    expect($response->getStatusCode())->toBe(409)
        ->and($this->api->errorCodes())->toContain('GROUP_MEMBERSHIP_SUSPENDED');
});

it('delivers account status changes by email', function () {
    authTestCreateAdmin($this, 'admin-email@example.com');
    $target = authTestCreateUser($this, 'target-email@example.com');
    $token = authTestAccessToken(authTestLogin($this, 'admin-email@example.com'));

    $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Motivo para e-mail.'],
        ['id' => $target->id()->value()],
        groupTestServer($token),
    );

    $deliveries = authTestEntityManager($this)->getConnection()->fetchAllAssociative(
        'SELECT channel, status FROM notification_deliveries ORDER BY created_at',
    );

    expect($deliveries)->toHaveCount(1)
        ->and($deliveries[0]['channel'])->toBe('EMAIL')
        ->and($deliveries[0]['status'])->toBe('SENT');
});
