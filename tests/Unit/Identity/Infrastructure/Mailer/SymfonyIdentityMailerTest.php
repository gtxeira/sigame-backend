<?php

declare(strict_types=1);

use App\Identity\Infrastructure\Mailer\MailDateFormatter;
use App\Identity\Infrastructure\Mailer\SymfonyIdentityMailer;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function mailerTestInstant(string $utc): Instant
{
    return Instant::fromDateTime(new DateTimeImmutable($utc, new DateTimeZone('UTC')));
}

/**
 * @return array{mailer: SymfonyIdentityMailer, captured: ?TemplatedEmail}
 */
function mailerTestSubject(string $frontendUrl = 'https://app.example.com'): array
{
    $captured = null;

    $mailer = Mockery::mock(MailerInterface::class);
    $mailer->shouldReceive('send')
        ->once()
        ->with(Mockery::on(static function (TemplatedEmail $email) use (&$captured): bool {
            $captured = $email;

            return true;
        }));

    $identityMailer = new SymfonyIdentityMailer(
        mailer: $mailer,
        fromAddress: 'noreply@sigame.com.br',
        fromName: 'SIGAME',
        frontendUrl: $frontendUrl,
    );

    return ['mailer' => $identityMailer, 'captured' => &$captured];
}

function mailerTestRender(TemplatedEmail $email): string
{
    $twig = new Environment(new FilesystemLoader(__DIR__.'/../../../../../templates'));

    return $twig->render((string) $email->getHtmlTemplate(), $email->getContext());
}

it('centralizes the brazilian timezone and the readable format', function () {
    expect(MailDateFormatter::TIMEZONE)->toBe('America/Sao_Paulo')
        ->and(MailDateFormatter::FORMAT)->toBe('d/m/Y H:i');
});

it('renders the verification email date in America/Sao_Paulo', function () {
    $subject = mailerTestSubject();

    $subject['mailer']->sendEmailVerification(
        to: Email::fromString('user@example.com')->value(),
        token: 'token-123',
        expiresAt: mailerTestInstant('2026-11-07T02:30:00+00:00'),
    );

    $email = $subject['captured'];
    expect($email)->toBeInstanceOf(TemplatedEmail::class);

    $rendered = mailerTestRender($email);

    expect($rendered)
        ->toContain('06/11/2026 23:30')
        ->not->toContain('07/11/2026 02:30')
        ->toContain('https://app.example.com/verificar-email?token=token-123')
        ->and($email->getSubject())->toBe('Confirme seu e-mail no SIGAME')
        ->and($email->getTo()[0]->getAddress())->toBe('user@example.com')
        ->and($email->getFrom()[0]->getAddress())->toBe('noreply@sigame.com.br')
        ->and($email->getFrom()[0]->getName())->toBe('SIGAME');
});

it('renders the password reset email date in America/Sao_Paulo', function () {
    $subject = mailerTestSubject();

    $subject['mailer']->sendPasswordReset(
        to: Email::fromString('user@example.com')->value(),
        token: 'reset-456',
        expiresAt: mailerTestInstant('2026-11-07T02:30:00+00:00'),
    );

    $email = $subject['captured'];
    expect($email)->toBeInstanceOf(TemplatedEmail::class);

    $rendered = mailerTestRender($email);

    expect($rendered)
        ->toContain('06/11/2026 23:30')
        ->not->toContain('07/11/2026 02:30')
        ->toContain('https://app.example.com/redefinir-senha?token=reset-456')
        ->and($email->getSubject())->toBe('Redefinição de senha do SIGAME');
});

it('shows the brazilian day when the expiration happens just after midnight utc', function () {
    $subject = mailerTestSubject();

    $subject['mailer']->sendPasswordReset(
        to: Email::fromString('user@example.com')->value(),
        token: 'reset-789',
        expiresAt: mailerTestInstant('2026-11-07T03:00:00+00:00'),
    );

    $rendered = mailerTestRender($subject['captured']);

    expect($rendered)->toContain('07/11/2026 00:00');
});
