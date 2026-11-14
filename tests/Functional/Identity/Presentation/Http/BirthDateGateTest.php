<?php

declare(strict_types=1);

use App\Identity\Domain\User\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * @return array{0: User, 1: array<string, string>}
 */
function birthDateGateTestIncompleteUser(
    FunctionalTestCase $test,
    string $email = 'birth-gate@example.com',
): array {
    $user = authTestCreateUser($test, $email, birthDate: null);
    $accessToken = authTestAccessToken(authTestLogin($test, $email));

    return [$user, ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken]];
}

it('lets an incomplete account read its profile and set the birth date', function () {
    [, $server] = birthDateGateTestIncompleteUser($this);

    $me = $this->api->get('app_me', server: $server);

    expect($me->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($me)['data']['birthDate'])->toBeNull();

    $update = $this->api->patch('app_me_birth_date', ['birthDate' => '1990-05-12'], server: $server);

    expect($update->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($update)['data']['birthDate'])->toBe('1990-05-12');
});

it('blocks platform routes for an incomplete account', function () {
    [, $server] = birthDateGateTestIncompleteUser($this);

    $progress = $this->api->get('app_my_progress', server: $server);
    $groups = $this->api->get('app_group_list', server: $server);

    expect($progress->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['USER_PROFILE_INCOMPLETE'])
        ->and($groups->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['USER_PROFILE_INCOMPLETE']);
});

it('unblocks the platform after the birth date is set', function () {
    [, $server] = birthDateGateTestIncompleteUser($this);

    $this->api->patch('app_me_birth_date', ['birthDate' => '1990-05-12'], server: $server);

    $response = $this->api->get('app_my_progress', server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

it('keeps authentication routes available for an incomplete account', function () {
    [, $server] = birthDateGateTestIncompleteUser($this);

    $logout = $this->api->post('app_auth_logout', server: $server);

    expect($logout->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);
});

it('exempts admin accounts from the birth date gate', function () {
    $admin = authTestCreateUser($this, 'birth-gate-admin@example.com', birthDate: null);
    $admin->grantAdminRole();
    authTestUserRepository($this)->save($admin);

    $accessToken = authTestAccessToken(authTestLogin($this, 'birth-gate-admin@example.com'));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $response = $this->api->get('app_admin_user_list', server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

it('blocks an active underage account as a backstop', function () {
    // A criação normal já removeria/suspenderia; aqui simulamos um registro
    // legado que ainda está ativo para validar a defesa em profundidade.
    authTestCreateUser($this, 'birth-gate-underage@example.com', birthDate: '2015-01-01');
    $accessToken = authTestAccessToken(authTestLogin($this, 'birth-gate-underage@example.com'));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $response = $this->api->get('app_my_progress', server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['USER_UNDER_MINIMUM_AGE']);
});
