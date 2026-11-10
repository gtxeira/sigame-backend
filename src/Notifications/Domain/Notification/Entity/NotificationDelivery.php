<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\Entity;

use App\Notifications\Domain\Notification\ValueObject\DeliveryStatus;
use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationDeliveryId;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entrega de uma notificação em um canal externo.
 */
#[ORM\Entity]
#[ORM\Table(name: 'notification_deliveries')]
#[ORM\UniqueConstraint(
    name: 'uniq_notification_deliveries_channel',
    columns: ['notification_id', 'channel'],
)]
final class NotificationDelivery
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'notification_delivery_id', unique: true)]
        private NotificationDeliveryId $id,

        #[ORM\ManyToOne(targetEntity: Notification::class)]
        #[ORM\JoinColumn(name: 'notification_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Notification $notification,

        #[ORM\Column(type: 'string', length: 20, enumType: NotificationChannel::class)]
        private NotificationChannel $channel,

        #[ORM\Column(type: 'string', length: 20, enumType: DeliveryStatus::class)]
        private DeliveryStatus $status,

        #[ORM\Column(type: 'integer', options: ['default' => 0])]
        private int $attempts,

        #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
        private ?string $lastError,

        #[ORM\Column(name: 'sent_at', type: 'instant', nullable: true)]
        private ?Instant $sentAt,

        #[ORM\Column(name: 'created_at', type: 'instant')]
        private Instant $createdAt,
    ) {
    }

    public static function pending(
        Notification $notification,
        NotificationChannel $channel,
        Instant $createdAt,
    ): self {
        return new self(
            id: NotificationDeliveryId::generate(),
            notification: $notification,
            channel: $channel,
            status: DeliveryStatus::PENDING,
            attempts: 0,
            lastError: null,
            sentAt: null,
            createdAt: $createdAt,
        );
    }

    public function markSent(Instant $now): void
    {
        $this->status = DeliveryStatus::SENT;
        $this->sentAt = $now;
        $this->lastError = null;
        ++$this->attempts;
    }

    public function markFailed(string $error): void
    {
        $this->status = DeliveryStatus::FAILED;
        $this->lastError = mb_substr($error, 0, 2000);
        ++$this->attempts;
    }

    public function markSkipped(string $reason): void
    {
        $this->status = DeliveryStatus::SKIPPED;
        $this->lastError = mb_substr($reason, 0, 2000);
        ++$this->attempts;
    }

    public function id(): NotificationDeliveryId
    {
        return $this->id;
    }

    public function notification(): Notification
    {
        return $this->notification;
    }

    public function channel(): NotificationChannel
    {
        return $this->channel;
    }

    public function status(): DeliveryStatus
    {
        return $this->status;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function sentAt(): ?Instant
    {
        return $this->sentAt;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }
}
