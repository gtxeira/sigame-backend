<?php

declare(strict_types=1);

use App\Identity\Application\Service\AccessTokenGeneratorInterface;
use App\Identity\Domain\User\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * Emite um token de acesso direto para a conta informada.
 *
 * O login é bloqueado para conta não verificada, então o gate de requisição é
 * exercitado com um token mintado fora do fluxo de autenticação — o mesmo
 * cenário de um token antigo/legado que ainda circula.
 *
 * @return array{0: User, 1: array<string, string>}
 */
function emailGateTestUnverifiedUser(
    FunctionalTestCase $test,
    string $email = 'email-gate@example.com',
): array {
    $user = authTestCreateUser($test, $email, verified: false);

    /* @phpstan-ignore-next-line */
    $generator = $test->api->getKernelBrowser()->getContainer()->get(AccessTokenGeneratorInterface::class);
    $accessToken = $generator->issue($user->id())->token;

    return [$user, ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken]];
}

it('lets an unverified account read its own profile', function () {
    [, $server] = emailGateTestUnverifiedUser($this);

    $me = $this->api->get('app_me', server: $server);

    expect($me->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($me)['data']['emailVerified'])->toBeFalse();
});

it('blocks platform routes for an unverified account', function () {
    [, $server] = emailGateTestUnverifiedUser($this);

    $progress = $this->api->get('app_my_progress', server: $server);
    $groups = $this->api->get('app_group_list', server: $server);

    expect($progress->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['EMAIL_NOT_VERIFIED'])
        ->and($groups->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($this->api->errorCodes())->toBe(['EMAIL_NOT_VERIFIED']);
});

it('unblocks the platform after the email is verified', function () {
    [$user, $server] = emailGateTestUnverifiedUser($this);

    $user->verifyEmail();
    authTestUserRepository($this)->save($user);

    $response = $this->api->get('app_my_progress', server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

it('keeps authentication routes available for an unverified account', function () {
    [, $server] = emailGateTestUnverifiedUser($this);

    $logout = $this->api->post('app_auth_logout', server: $server);

    expect($logout->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);
});

it('exempts admin accounts from the email verification gate', function () {
    $admin = authTestCreateUser($this, 'email-gate-admin@example.com', verified: false);
    $admin->grantAdminRole();
    authTestUserRepository($this)->save($admin);

    /* @phpstan-ignore-next-line */
    $generator = $this->api->getKernelBrowser()->getContainer()->get(AccessTokenGeneratorInterface::class);
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$generator->issue($admin->id())->token];

    $response = $this->api->get('app_admin_user_list', server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});
