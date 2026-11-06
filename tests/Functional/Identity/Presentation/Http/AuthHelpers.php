<?php

declare(strict_types=1);

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Infrastructure\Security\LcobucciAccessTokenGenerator;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;
use Tests\Support\RecordingIdentityMailer;

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

/**
 * Emite um access token já expirado para o usuário informado.
 *
 * Usa um clock no passado, mas o mesmo segredo/emissor/audiência da aplicação,
 * para que o token seja autêntico e apenas a validade esteja vencida.
 */
function authTestExpiredAccessToken(UserId $userId): string
{
    $generator = new LcobucciAccessTokenGenerator(
        secret: (string) ($_SERVER['ACCESS_TOKEN_SECRET'] ?? $_ENV['ACCESS_TOKEN_SECRET'] ?? ''),
        issuer: (string) ($_SERVER['ACCESS_TOKEN_ISSUER'] ?? $_ENV['ACCESS_TOKEN_ISSUER'] ?? 'sigame'),
        audience: (string) ($_SERVER['ACCESS_TOKEN_AUDIENCE'] ?? $_ENV['ACCESS_TOKEN_AUDIENCE'] ?? 'sigame-api'),
        ttlSeconds: 900,
        clock: new MockClock('2020-01-01 00:00:00 UTC'),
    );

    return $generator->issue($userId)->token;
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

function authTestSocialFlowCookie(Response $response): ?string
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ('social_flow' === $cookie->getName()) {
            return $cookie->getValue();
        }
    }

    return null;
}

function authTestSetSocialFlowCookie(FunctionalTestCase $test, string $value): void
{
    $test->api->getKernelBrowser()->getCookieJar()->set(
        new Cookie('social_flow', $value, null, '/api/v1'),
    );
}

function authTestMailer(FunctionalTestCase $test): RecordingIdentityMailer
{
    /** @var RecordingIdentityMailer $mailer */
    /* @phpstan-ignore-next-line */
    $mailer = $test->api->getKernelBrowser()->getContainer()->get(RecordingIdentityMailer::class);

    return $mailer;
}

function authTestUserRepository(FunctionalTestCase $test): UserRepositoryInterface
{
    /** @var UserRepositoryInterface $repository */
    /* @phpstan-ignore-next-line */
    $repository = $test->api->getKernelBrowser()->getContainer()->get(UserRepositoryInterface::class);

    return $repository;
}

function authTestEntityManager(FunctionalTestCase $test): EntityManagerInterface
{
    /** @var EntityManagerInterface $em */
    /* @phpstan-ignore-next-line */
    $em = $test->api->getKernelBrowser()->getContainer()->get(EntityManagerInterface::class);

    return $em;
}

/**
 * Força a expiração de um token de identidade já emitido.
 *
 * O UPDATE roda na transação do teste e o EntityManager é limpo para que o
 * próximo request hidrate o token com a nova data.
 */
function authTestExpireIdentityToken(FunctionalTestCase $test, string $rawToken): void
{
    $em = authTestEntityManager($test);

    $em->getConnection()->executeStatement(
        "UPDATE identity_tokens SET expires_at = now() - interval '1 hour' WHERE token_hash = ?",
        [hash('sha256', $rawToken)],
    );
    $em->clear();
}

function authTestSocialFlowCookieObject(Response $response): ?\Symfony\Component\HttpFoundation\Cookie
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ('social_flow' === $cookie->getName()) {
            return $cookie;
        }
    }

    return null;
}

function authTestCreateSocialUser(
    FunctionalTestCase $test,
    string $email,
    bool $suspended = false,
): User {
    $user = User::registerWithProvider(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google:'.$email),
        email: Email::fromString($email)->value(),
        name: 'Social User',
    );

    if ($suspended) {
        $user->suspend('fraud');
    }

    authTestUserRepository($test)->save($user);

    return $user;
}
