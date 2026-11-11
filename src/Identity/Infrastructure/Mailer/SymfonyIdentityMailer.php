<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer;

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Adapter Symfony Mailer para os e-mails de identidade.
 *
 * O token vai no link do front-end; a API só recebe o token de volta.
 */
final readonly class SymfonyIdentityMailer implements IdentityMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private string $fromAddress,
        #[Autowire(env: 'MAILER_FROM_NAME')]
        private string $fromName,
        #[Autowire(env: 'FRONTEND_URL')]
        private string $frontendUrl,
    ) {
    }

    public function sendEmailVerification(Email $to, string $token, Instant $expiresAt): void
    {
        $this->send(
            to: $to,
            subject: 'Confirme seu e-mail no SIGAME',
            template: 'email/verify_email.html.twig',
            context: [
                'url' => $this->frontendUrl.'/verificar-email?token='.urlencode($token),
                'expiresAt' => MailDateFormatter::format($expiresAt),
            ],
        );
    }

    public function sendPasswordReset(Email $to, string $token, Instant $expiresAt): void
    {
        $this->send(
            to: $to,
            subject: 'Redefinição de senha do SIGAME',
            template: 'email/password_reset.html.twig',
            context: [
                'url' => $this->frontendUrl.'/redefinir-senha?token='.urlencode($token),
                'expiresAt' => MailDateFormatter::format($expiresAt),
            ],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(Email $to, string $subject, string $template, array $context): void
    {
        $email = new TemplatedEmail()
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to->value())
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        $this->mailer->send($email);
    }
}
