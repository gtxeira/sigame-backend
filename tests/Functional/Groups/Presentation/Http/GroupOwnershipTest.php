<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

it('transfers ownership and promotes the new owner to admin', function () {
    $context = groupOwnershipContext($this);

    $response = $this->api->put(
        'app_group_ownership_transfer',
        ['membershipId' => $context['memberMembershipId']],
        ['id' => $context['groupId']],
        groupTestServer($context['ownerToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($response)['data'];
    expect($data['ownerId'])->toBe($context['member']->id()->value())
        ->and($data['viewerMembership']['isOwner'])->toBeFalse();

    $asMember = $this->api->get(
        'app_group_show',
        [],
        ['id' => $context['groupId']],
        groupTestServer($context['memberToken']),
    );

    expect(authTestBody($asMember)['data']['viewerMembership']['role'])->toBe('admin')
        ->and(authTestBody($asMember)['data']['viewerMembership']['isOwner'])->toBeTrue();
});

it('rejects an ownership transfer made by someone who is not the owner', function () {
    $context = groupOwnershipContext($this);

    $response = $this->api->put(
        'app_group_ownership_transfer',
        ['membershipId' => $context['ownerMembershipId']],
        ['id' => $context['groupId']],
        groupTestServer($context['memberToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['GROUP_OWNER_REQUIRED']);
});

it('rejects an ownership transfer to a pending member', function () {
    $context = groupOwnershipContext($this);

    groupTestCreateUser($this, 'ownership-pending@example.com');
    $pendingToken = groupTestAccessToken($this, 'ownership-pending@example.com');
    $pendingMembership = groupTestRequestMembership($this, $pendingToken, $context['groupId']);

    $response = $this->api->put(
        'app_group_ownership_transfer',
        ['membershipId' => $pendingMembership],
        ['id' => $context['groupId']],
        groupTestServer($context['ownerToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_MEMBERSHIP_NOT_APPROVED']);
});

it('rejects an ownership transfer to a suspended account', function () {
    $context = groupOwnershipContext($this);

    authTestCreateAdmin($this, 'ownership-admin@example.com');
    $adminToken = authTestAccessToken(authTestLogin($this, 'ownership-admin@example.com'));

    $this->api->post(
        'app_admin_user_suspend',
        ['reason' => 'Descumprimento das regras.'],
        ['id' => $context['member']->id()->value()],
        groupTestServer($adminToken),
    );

    $response = $this->api->put(
        'app_group_ownership_transfer',
        ['membershipId' => $context['memberMembershipId']],
        ['id' => $context['groupId']],
        groupTestServer($context['ownerToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($this->api->errorCodes())->toBe(['GROUP_MEMBERSHIP_SUSPENDED']);
});

it('allows transferring ownership of an archived group', function () {
    $context = groupOwnershipContext($this);

    $this->api->post(
        'app_group_archive',
        ['reason' => 'Pausa nas atividades.'],
        ['id' => $context['groupId']],
        groupTestServer($context['ownerToken']),
    );

    $response = $this->api->put(
        'app_group_ownership_transfer',
        ['membershipId' => $context['memberMembershipId']],
        ['id' => $context['groupId']],
        groupTestServer($context['ownerToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($response)['data']['ownerId'])->toBe($context['member']->id()->value());
});

it('only lets the owner delete the group', function () {
    $context = groupOwnershipContext($this);

    $this->api->patch(
        'app_group_membership_role',
        ['role' => 'admin'],
        ['id' => $context['groupId'], 'membershipId' => $context['memberMembershipId']],
        groupTestServer($context['ownerToken']),
    );

    $rejected = $this->api->delete(
        'app_group_delete',
        ['reason' => 'Encerrado.'],
        ['id' => $context['groupId']],
        groupTestServer($context['memberToken']),
    );

    expect($rejected->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['GROUP_OWNER_REQUIRED']);

    $deleted = $this->api->delete(
        'app_group_delete',
        ['reason' => 'Encerrado.'],
        ['id' => $context['groupId']],
        groupTestServer($context['ownerToken']),
    );

    expect($deleted->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($deleted)['data']['deleted'])->toBeTrue();
});
