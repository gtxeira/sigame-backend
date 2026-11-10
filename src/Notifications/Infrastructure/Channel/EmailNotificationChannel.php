<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Channel;

use App\Notifications\Application\Service\NotificationChannelInterface;
use App\Notifications\Domain\Notification\Entity\Notification;
use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Shared\Application\User\UserContact;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Entrega notificações por e-mail transacional.
 */
#[AutoconfigureTag('app.notification_channel')]
final readonly class EmailNotificationChannel implements NotificationChannelInterface
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private string $fromAddress,
        #[Autowire(env: 'MAILER_FROM_NAME')]
        private string $fromName,
    ) {
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::EMAIL;
    }

    public function send(Notification $notification, UserContact $recipient): void
    {
        $email = new TemplatedEmail()
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($recipient->email)
            ->subject($notification->title())
            ->htmlTemplate('email/notification.html.twig')
            ->context([
                'title' => $notification->title(),
                'body' => $notification->body(),
            ]);

        $this->mailer->send($email);
    }
}
