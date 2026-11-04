<?php

declare(strict_types=1);

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

function authTestCreateUser(
    FunctionalTestCase $test,
    string $email = 'auth@example.com',
    string $password = 'secret123',
    ?string $cpf = null,
): User {
    $container = $test->api->getKernelBrowser()->getContainer();

    $user = User::register(
        cpf: null === $cpf ? null : Cpf::fromString($cpf)->value(),
        email: Email::fromString($email)->value(),
        /* @phpstan-ignore-next-line */
        password: $container->get(PasswordHasherInterface::class)->hash($password),
        name: 'Auth Test User',
    );

    /* @phpstan-ignore-next-line */
    $container->get(UserRepositoryInterface::class)->save($user);

    return $user;
}

function authTestLogin(
    FunctionalTestCase $test,
    string $email = 'auth@example.com',
    string $password = 'secret123',
): Response {
    return $test->api->post('app_auth_login', [
        'email' => $email,
        'password' => $password,
    ]);
}

/**
 * @return array<string, mixed>
 */
function authTestBody(Response $response): array
{
    /** @var array<string, mixed> $body */
    $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $body;
}

function authTestAccessToken(Response $response): string
{
    return (string) authTestBody($response)['data']['accessToken'];
}

function authTestRefreshCookie(Response $response): ?string
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ('refresh_token' === $cookie->getName()) {
            return $cookie->getValue();
        }
    }

    return null;
}

function authTestSetRefreshCookie(FunctionalTestCase $test, string $value): void
{
    $jar = $test->api->getKernelBrowser()->getCookieJar();
    $jar->clear();
    $jar->set(new Cookie('refresh_token', $value, null, '/api/v1/auth'));
}
