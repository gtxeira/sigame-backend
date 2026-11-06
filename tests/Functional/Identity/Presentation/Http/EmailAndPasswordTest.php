<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

function emailTestUser(FunctionalTestCase $test, string $email): ?\App\Identity\Domain\User\Entity\User
{
    return authTestUserRepository($test)->findByEmail(Email::fromString($email)->value());
}

it('sends a verification email on registration and verifies the address', function () {
    $email = 'verify-me@example.com';

    $register = $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Verify Me',
        'password' => 'secret123',
    ]);

    expect($register->getStatusCode())->toBe(Response::HTTP_CREATED);

    $token = authTestMailer($this)->lastToken('verification', $email);

    expect($token)->toBeString()->not->toBeEmpty();

    $verify = $this->api->post('app_auth_email_verify', ['token' => $token]);

    expect($verify->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and(emailTestUser($this, $email)?->isEmailVerified())->toBeTrue();

    $reuse = $this->api->post('app_auth_email_verify', ['token' => $token]);

    expect($reuse->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['EMAIL_VERIFICATION_TOKEN_INVALID']);
});

it('resends the verification and invalidates the previous token', function () {
    $email = 'resend@example.com';

    $register = $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Resend Me',
        'password' => 'secret123',
    ]);
    expect($register->getStatusCode())->toBe(Response::HTTP_CREATED);

    $firstToken = authTestMailer($this)->lastToken('verification', $email);
    $accessToken = authTestAccessToken(authTestLogin($this, $email));

    $resend = $this->api->post('app_me_email_resend', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
    ]);

    expect($resend->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

    $secondToken = authTestMailer($this)->lastToken('verification', $email);

    expect($secondToken)->toBeString()->not->toBe($firstToken);

    $old = $this->api->post('app_auth_email_verify', ['token' => $firstToken]);

    expect($old->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);

    $new = $this->api->post('app_auth_email_verify', ['token' => $secondToken]);

    expect($new->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);
});

it('does not reveal whether an email exists when requesting a password reset', function () {
    authTestCreateUser($this, 'exists@example.com');

    $known = $this->api->post('app_auth_password_forgot', ['email' => 'exists@example.com']);
    $unknown = $this->api->post('app_auth_password_forgot', ['email' => 'unknown@example.com']);

    expect($known->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($unknown->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and(authTestMailer($this)->count('password_reset', 'exists@example.com'))->toBe(1)
        ->and(authTestMailer($this)->count('password_reset', 'unknown@example.com'))->toBe(0);
});

it('resets the password and revokes every session', function () {
    $email = 'reset@example.com';
    authTestCreateUser($this, $email);
    $login = authTestLogin($this, $email);
    $oldRefresh = (string) authTestRefreshCookie($login);

    $this->api->post('app_auth_password_forgot', ['email' => $email]);
    $token = authTestMailer($this)->lastToken('password_reset', $email);

    expect($token)->toBeString()->not->toBeEmpty();

    $reset = $this->api->post('app_auth_password_reset', [
        'token' => $token,
        'password' => 'new-secret-123',
    ]);

    expect($reset->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

    $oldPassword = authTestLogin($this, $email, 'secret123');
    $newPassword = authTestLogin($this, $email, 'new-secret-123');

    expect($oldPassword->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($newPassword->getStatusCode())->toBe(Response::HTTP_OK);

    authTestSetRefreshCookie($this, $oldRefresh);

    $refresh = $this->api->post('app_auth_refresh');

    expect($refresh->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);

    $reuse = $this->api->post('app_auth_password_reset', [
        'token' => $token,
        'password' => 'another-secret-123',
    ]);

    expect($reuse->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['PASSWORD_RESET_TOKEN_INVALID']);
});

it('verifies an unverified email when the password is reset', function () {
    $email = 'reset-verifies@example.com';
    $user = authTestCreateUser($this, $email);

    expect($user->isEmailVerified())->toBeFalse();

    $this->api->post('app_auth_password_forgot', ['email' => $email]);
    $token = (string) authTestMailer($this)->lastToken('password_reset', $email);

    $reset = $this->api->post('app_auth_password_reset', [
        'token' => $token,
        'password' => 'new-secret-123',
    ]);

    expect($reset->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and(emailTestUser($this, $email)?->isEmailVerified())->toBeTrue();
});

it('changes the password and rejects the wrong current password', function () {
    $email = 'change@example.com';
    authTestCreateUser($this, $email);
    $accessToken = authTestAccessToken(authTestLogin($this, $email));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    $wrong = $this->api->post('app_me_password_change', [
        'currentPassword' => 'wrong-password',
        'newPassword' => 'new-secret-123',
    ], server: $server);

    expect($wrong->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['PASSWORD_CURRENT_INVALID']);

    $change = $this->api->post('app_me_password_change', [
        'currentPassword' => 'secret123',
        'newPassword' => 'new-secret-123',
    ], server: $server);

    expect($change->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

    expect(authTestLogin($this, $email, 'secret123')->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and(authTestLogin($this, $email, 'new-secret-123')->getStatusCode())->toBe(Response::HTTP_OK);
});

it('rate limits repeated registrations for the same email', function () {
    for ($attempt = 0; $attempt < 5; ++$attempt) {
        $this->api->post('app_auth_register', [
            'email' => 'register-rate@example.com',
            'name' => 'Rate Limit',
            'password' => 'secret123',
        ]);
    }

    $response = $this->api->post('app_auth_register', [
        'email' => 'register-rate@example.com',
        'name' => 'Rate Limit',
        'password' => 'secret123',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS)
        ->and($this->api->errorCodes())->toBe(['TOO_MANY_REQUESTS']);
});

it('announces the expiration date in America/Sao_Paulo', function () {
    $email = 'timezone@example.com';

    $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Timezone User',
        'password' => 'secret123',
    ]);

    $expiresAt = authTestMailer($this)->lastExpiresAt('verification', $email);

    expect($expiresAt)->toBeString()->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/');

    $announced = DateTimeImmutable::createFromFormat(
        'd/m/Y H:i',
        (string) $expiresAt,
        new DateTimeZone('America/Sao_Paulo'),
    );

    assert($announced instanceof DateTimeImmutable);

    // Se o e-mail exibisse UTC, a diferença ficaria 3 horas menor.
    expect($announced->getTimestamp() - time())
        ->toBeGreaterThan(86_280)
        ->toBeLessThan(86_520);
});

it('rejects an expired email verification token', function () {
    $email = 'expired-verification@example.com';

    $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Expired Verification',
        'password' => 'secret123',
    ]);
    $token = (string) authTestMailer($this)->lastToken('verification', $email);

    authTestExpireIdentityToken($this, $token);

    $response = $this->api->post('app_auth_email_verify', ['token' => $token]);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['EMAIL_VERIFICATION_TOKEN_EXPIRED']);
});

it('rejects an expired password reset token', function () {
    $email = 'expired-reset@example.com';
    authTestCreateUser($this, $email);

    $this->api->post('app_auth_password_forgot', ['email' => $email]);
    $token = (string) authTestMailer($this)->lastToken('password_reset', $email);

    authTestExpireIdentityToken($this, $token);

    $response = $this->api->post('app_auth_password_reset', [
        'token' => $token,
        'password' => 'new-secret-123',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['PASSWORD_RESET_TOKEN_EXPIRED']);
});

it('creates a local password for a social-only account through reset', function () {
    $email = 'social-reset@example.com';
    authTestCreateSocialUser($this, $email);

    $this->api->post('app_auth_password_forgot', ['email' => $email]);
    $token = (string) authTestMailer($this)->lastToken('password_reset', $email);

    $reset = $this->api->post('app_auth_password_reset', [
        'token' => $token,
        'password' => 'local-secret-123',
    ]);

    expect($reset->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

    $user = emailTestUser($this, $email);

    expect($user?->hasLocalPassword())->toBeTrue()
        ->and($user?->identities())->toHaveCount(2)
        ->and(authTestLogin($this, $email, 'local-secret-123')->getStatusCode())->toBe(Response::HTTP_OK);
});

it('rate limits repeated email verification attempts', function () {
    for ($attempt = 0; $attempt < 10; ++$attempt) {
        $this->api->post('app_auth_email_verify', ['token' => 'invalid-'.$attempt]);
    }

    $response = $this->api->post('app_auth_email_verify', ['token' => 'invalid-10']);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS)
        ->and($this->api->errorCodes())->toBe(['TOO_MANY_REQUESTS']);
});

it('rate limits repeated email verification resends', function () {
    $email = 'resend-limit@example.com';
    authTestCreateUser($this, $email);
    $accessToken = authTestAccessToken(authTestLogin($this, $email));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    for ($attempt = 0; $attempt < 3; ++$attempt) {
        $this->api->post('app_me_email_resend', server: $server);
    }

    $response = $this->api->post('app_me_email_resend', server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rate limits repeated password reset requests', function () {
    for ($attempt = 0; $attempt < 5; ++$attempt) {
        $this->api->post('app_auth_password_forgot', ['email' => 'forgot-limit@example.com']);
    }

    $response = $this->api->post('app_auth_password_forgot', ['email' => 'forgot-limit@example.com']);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rate limits repeated password reset submissions', function () {
    for ($attempt = 0; $attempt < 10; ++$attempt) {
        $this->api->post('app_auth_password_reset', [
            'token' => 'invalid-'.$attempt,
            'password' => 'new-secret-123',
        ]);
    }

    $response = $this->api->post('app_auth_password_reset', [
        'token' => 'invalid-10',
        'password' => 'new-secret-123',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rate limits repeated password changes', function () {
    $email = 'change-limit@example.com';
    authTestCreateUser($this, $email);
    $accessToken = authTestAccessToken(authTestLogin($this, $email));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    for ($attempt = 0; $attempt < 5; ++$attempt) {
        $this->api->post('app_me_password_change', [
            'currentPassword' => 'wrong-password',
            'newPassword' => 'new-secret-123',
        ], server: $server);
    }

    $response = $this->api->post('app_me_password_change', [
        'currentPassword' => 'wrong-password',
        'newPassword' => 'new-secret-123',
    ], server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});
