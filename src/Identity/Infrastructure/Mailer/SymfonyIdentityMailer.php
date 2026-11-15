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
 * O código OTP vai no corpo da mensagem; o link leva apenas o e-mail para
 * pré-preencher a tela, nunca uma credencial.
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

    public function sendEmailVerification(Email $to, string $code, Instant $expiresAt): void
    {
        $this->send(
            to: $to,
            subject: 'Seu código de confirmação do SIGAME',
            template: 'email/verify_email.html.twig',
            context: [
                'code' => $code,
                'url' => $this->frontendUrl.'/verificar-email?email='.urlencode($to->value()),
                'expiresAt' => MailDateFormatter::format($expiresAt),
            ],
        );
    }

    public function sendPasswordReset(Email $to, string $code, Instant $expiresAt): void
    {
        $this->send(
            to: $to,
            subject: 'Seu código para redefinir a senha do SIGAME',
            template: 'email/password_reset.html.twig',
            context: [
                'code' => $code,
                'url' => $this->frontendUrl.'/recuperar-senha?email='.urlencode($to->value()),
                'expiresAt' => MailDateFormatter::format($expiresAt),
            ],
        );
    }

    public function sendAccountExists(Email $to): void
    {
        $this->send(
            to: $to,
            subject: 'Sua conta no SIGAME já existe',
            template: 'email/account_exists.html.twig',
            context: [],
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
