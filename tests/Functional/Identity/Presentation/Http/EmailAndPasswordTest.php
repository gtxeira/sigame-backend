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
