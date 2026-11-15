<?php

declare(strict_types=1);

use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

function emailTestUser(FunctionalTestCase $test, string $email): ?\App\Identity\Domain\User\Entity\User
{
    return authTestUserRepository($test)->findByEmail(Email::fromString($email)->value());
}

function emailTestRequestResetCode(FunctionalTestCase $test, string $email): Response
{
    return $test->api->post('app_auth_password_forgot', ['email' => $email]);
}

function emailTestVerifyResetCode(FunctionalTestCase $test, string $email, string $code): Response
{
    return $test->api->post('app_auth_password_otp_verify', [
        'email' => $email,
        'code' => $code,
    ]);
}

function emailTestResetTicket(Response $response): string
{
    return (string) authTestBody($response)['data']['resetToken'];
}

it('sends a verification code on registration and verifies the address', function () {
    $email = 'verify-me@example.com';

    $register = $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Verify Me',
        'password' => 'secret123',
    ]);

    expect($register->getStatusCode())->toBe(Response::HTTP_CREATED);

    $code = authTestMailer($this)->lastCode('verification', $email);

    expect($code)->toBeString()->toMatch('/^\d{6}$/');

    $verify = $this->api->post('app_auth_email_verify', [
        'email' => $email,
        'code' => $code,
    ]);

    expect($verify->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and(emailTestUser($this, $email)?->isEmailVerified())->toBeTrue();

    $reuse = $this->api->post('app_auth_email_verify', [
        'email' => $email,
        'code' => $code,
    ]);

    expect($reuse->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['EMAIL_VERIFICATION_CODE_INVALID']);
});

it('resends the verification and invalidates the previous code', function () {
    $email = 'resend@example.com';

    $register = $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Resend Me',
        'password' => 'secret123',
    ]);
    expect($register->getStatusCode())->toBe(Response::HTTP_CREATED);

    $firstCode = authTestMailer($this)->lastCode('verification', $email);
    $accessToken = authTestAccessToken(authTestLogin($this, $email));

    $resend = $this->api->post('app_auth_email_resend', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
    ]);

    expect($resend->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

    $secondCode = authTestMailer($this)->lastCode('verification', $email);

    expect($secondCode)->toBeString()->not->toBe($firstCode);

    $old = $this->api->post('app_auth_email_verify', [
        'email' => $email,
        'code' => $firstCode,
    ]);

    expect($old->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);

    $new = $this->api->post('app_auth_email_verify', [
        'email' => $email,
        'code' => $secondCode,
    ]);

    expect($new->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);
});

it('invalidates the code after five wrong attempts', function () {
    $email = 'attempts@example.com';

    $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Attempts User',
        'password' => 'secret123',
    ]);

    $code = (string) authTestMailer($this)->lastCode('verification', $email);
    $wrong = '000000' === $code ? '000001' : '000000';

    for ($attempt = 0; $attempt < 4; ++$attempt) {
        $response = $this->api->post('app_auth_email_verify', [
            'email' => $email,
            'code' => $wrong,
        ]);

        expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->and($this->api->errorCodes())->toBe(['EMAIL_VERIFICATION_CODE_INVALID']);
    }

    $fifth = $this->api->post('app_auth_email_verify', [
        'email' => $email,
        'code' => $wrong,
    ]);

    expect($fifth->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['EMAIL_VERIFICATION_CODE_ATTEMPTS_EXCEEDED']);

    $correct = $this->api->post('app_auth_email_verify', [
        'email' => $email,
        'code' => $code,
    ]);

    expect($correct->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['EMAIL_VERIFICATION_CODE_INVALID']);
});

it('does not reveal whether an email exists when requesting a password reset', function () {
    authTestCreateUser($this, 'exists@example.com');

    $known = emailTestRequestResetCode($this, 'exists@example.com');
    $unknown = emailTestRequestResetCode($this, 'unknown@example.com');

    expect($known->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($unknown->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and(authTestMailer($this)->count('password_reset', 'exists@example.com'))->toBe(1)
        ->and(authTestMailer($this)->count('password_reset', 'unknown@example.com'))->toBe(0);
});

it('sends the reset code to the canonical email regardless of the request casing', function () {
    $email = 'canonical-reset@example.com';
    authTestCreateUser($this, $email);

    $response = emailTestRequestResetCode($this, 'CANONICAL-RESET@EXAMPLE.COM');

    expect($response->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and(authTestMailer($this)->count('password_reset', $email))->toBe(1);
});

it('resets the password in two steps and revokes every session', function () {
    $email = 'reset@example.com';
    authTestCreateUser($this, $email);
    $login = authTestLogin($this, $email);
    $oldRefresh = (string) authTestRefreshCookie($login);

    emailTestRequestResetCode($this, $email);
    $code = (string) authTestMailer($this)->lastCode('password_reset', $email);

    $verify = emailTestVerifyResetCode($this, $email, $code);

    expect($verify->getStatusCode())->toBe(Response::HTTP_OK);

    $ticket = emailTestResetTicket($verify);

    expect($ticket)->not->toBe('');

    $reset = $this->api->post('app_auth_password_reset', [
        'resetToken' => $ticket,
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
        'resetToken' => $ticket,
        'password' => 'another-secret-123',
    ]);

    expect($reuse->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['PASSWORD_RESET_TICKET_INVALID']);
});

it('verifies an unverified email when the password is reset', function () {
    $email = 'reset-verifies@example.com';
    $user = authTestCreateUser($this, $email);

    expect($user->isEmailVerified())->toBeFalse();

    emailTestRequestResetCode($this, $email);
    $code = (string) authTestMailer($this)->lastCode('password_reset', $email);
    $verify = emailTestVerifyResetCode($this, $email, $code);
    $ticket = emailTestResetTicket($verify);

    $reset = $this->api->post('app_auth_password_reset', [
        'resetToken' => $ticket,
        'password' => 'new-secret-123',
    ]);

    expect($reset->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and(emailTestUser($this, $email)?->isEmailVerified())->toBeTrue();
});

it('rejects a reset code after too many wrong attempts', function () {
    $email = 'reset-attempts@example.com';
    authTestCreateUser($this, $email);

    emailTestRequestResetCode($this, $email);
    $code = (string) authTestMailer($this)->lastCode('password_reset', $email);
    $wrong = '000000' === $code ? '000001' : '000000';

    for ($attempt = 0; $attempt < 5; ++$attempt) {
        $response = emailTestVerifyResetCode($this, $email, $wrong);

        expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    expect($this->api->errorCodes())->toBe(['PASSWORD_RESET_CODE_ATTEMPTS_EXCEEDED']);

    $correct = emailTestVerifyResetCode($this, $email, $code);

    expect($correct->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['PASSWORD_RESET_CODE_INVALID']);
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

it('announces the verification expiration in America/Sao_Paulo', function () {
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

    // O TTL é de 15 minutos; se o e-mail exibisse UTC, a diferença seria 3 horas menor.
    expect($announced->getTimestamp() - time())
        ->toBeGreaterThan(840)
        ->toBeLessThan(960);
});

it('rejects an expired email verification code', function () {
    $email = 'expired-verification@example.com';

    $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Expired Verification',
        'password' => 'secret123',
    ]);
    $code = (string) authTestMailer($this)->lastCode('verification', $email);

    authTestExpireOtpChallenge($this, $email, OtpPurpose::EmailVerification);

    $response = $this->api->post('app_auth_email_verify', [
        'email' => $email,
        'code' => $code,
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['EMAIL_VERIFICATION_CODE_EXPIRED']);
});

it('rejects an expired password reset code', function () {
    $email = 'expired-reset@example.com';
    authTestCreateUser($this, $email);

    emailTestRequestResetCode($this, $email);
    $code = (string) authTestMailer($this)->lastCode('password_reset', $email);

    authTestExpireOtpChallenge($this, $email, OtpPurpose::PasswordReset);

    $response = emailTestVerifyResetCode($this, $email, $code);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['PASSWORD_RESET_CODE_EXPIRED']);
});

it('rejects an expired password reset ticket', function () {
    $email = 'expired-ticket@example.com';
    authTestCreateUser($this, $email);

    emailTestRequestResetCode($this, $email);
    $code = (string) authTestMailer($this)->lastCode('password_reset', $email);
    $ticket = emailTestResetTicket(emailTestVerifyResetCode($this, $email, $code));

    $em = authTestEntityManager($this);
    $em->getConnection()->executeStatement(
        "UPDATE identity_password_reset_tickets SET expires_at = now() - interval '1 hour' WHERE token_hash = ?",
        [hash('sha256', $ticket)],
    );
    $em->clear();

    $response = $this->api->post('app_auth_password_reset', [
        'resetToken' => $ticket,
        'password' => 'new-secret-123',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['PASSWORD_RESET_TICKET_EXPIRED']);
});

it('creates a local password for a social-only account through reset', function () {
    $email = 'social-reset@example.com';
    authTestCreateSocialUser($this, $email);

    emailTestRequestResetCode($this, $email);
    $code = (string) authTestMailer($this)->lastCode('password_reset', $email);
    $ticket = emailTestResetTicket(emailTestVerifyResetCode($this, $email, $code));

    $reset = $this->api->post('app_auth_password_reset', [
        'resetToken' => $ticket,
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
        $this->api->post('app_auth_email_verify', [
            'email' => 'verify-rate@example.com',
            'code' => '000000',
        ]);
    }

    $response = $this->api->post('app_auth_email_verify', [
        'email' => 'verify-rate@example.com',
        'code' => '000000',
    ]);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS)
        ->and($this->api->errorCodes())->toBe(['TOO_MANY_REQUESTS']);
});

it('reports a conflict when resending verification for an already verified email', function () {
    $email = 'resend-verified@example.com';

    $this->api->post('app_auth_register', [
        'email' => $email,
        'name' => 'Already Verified',
        'password' => 'secret123',
    ]);

    $code = (string) authTestMailer($this)->lastCode('verification', $email);
    $this->api->post('app_auth_email_verify', ['email' => $email, 'code' => $code]);

    $accessToken = authTestAccessToken(authTestLogin($this, $email));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    for ($attempt = 0; $attempt < 4; ++$attempt) {
        $response = $this->api->post('app_auth_email_resend', server: $server);

        expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT);
    }

    expect($this->api->errorCodes())->toBe(['EMAIL_ALREADY_VERIFIED'])
        ->and(authTestMailer($this)->count('verification', $email))->toBe(1);
});

it('rate limits repeated email verification resends', function () {
    $email = 'resend-limit@example.com';
    authTestCreateUser($this, $email);
    $accessToken = authTestAccessToken(authTestLogin($this, $email));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];

    for ($attempt = 0; $attempt < 3; ++$attempt) {
        $this->api->post('app_auth_email_resend', server: $server);
    }

    $response = $this->api->post('app_auth_email_resend', server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rate limits repeated password reset requests', function () {
    for ($attempt = 0; $attempt < 3; ++$attempt) {
        emailTestRequestResetCode($this, 'forgot-limit@example.com');
    }

    $response = emailTestRequestResetCode($this, 'forgot-limit@example.com');

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rate limits repeated password reset code submissions', function () {
    for ($attempt = 0; $attempt < 10; ++$attempt) {
        emailTestVerifyResetCode($this, 'otp-limit@example.com', '000000');
    }

    $response = emailTestVerifyResetCode($this, 'otp-limit@example.com', '000000');

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rate limits repeated password reset submissions', function () {
    for ($attempt = 0; $attempt < 10; ++$attempt) {
        $this->api->post('app_auth_password_reset', [
            'resetToken' => 'invalid-'.$attempt,
            'password' => 'new-secret-123',
        ]);
    }

    $response = $this->api->post('app_auth_password_reset', [
        'resetToken' => 'invalid-10',
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
