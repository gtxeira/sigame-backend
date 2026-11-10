<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Notification\Entity\Notification;
use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Shared\Application\User\UserContact;

/**
 * Porta de envio por um canal externo.
 */
interface NotificationChannelInterface
{
    public function channel(): NotificationChannel;

    public function send(Notification $notification, UserContact $recipient): void;
}
