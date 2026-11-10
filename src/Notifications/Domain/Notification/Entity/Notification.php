<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\Entity;

use App\Notifications\Domain\Notification\ValueObject\DeduplicationKey;
use App\Notifications\Domain\Notification\ValueObject\NotificationBody;
use App\Notifications\Domain\Notification\ValueObject\NotificationCategory;
use App\Notifications\Domain\Notification\ValueObject\NotificationId;
use App\Notifications\Domain\Notification\ValueObject\NotificationPayload;
use App\Notifications\Domain\Notification\ValueObject\NotificationTitle;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Notificação persistida no inbox do destinatário.
 *
 * O registro é canônico e independente de frontend: título, corpo e dados
 * estruturados são consumidos pela API, pelo Mercure e por canais externos.
 */
#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
#[ORM\UniqueConstraint(
    name: 'uniq_notifications_recipient_deduplication',
    columns: ['recipient_id', 'deduplication_key'],
)]
#[ORM\Index(name: 'idx_notifications_recipient_created', columns: ['recipient_id', 'created_at'])]
#[ORM\Index(
    name: 'idx_notifications_recipient_unread',
    columns: ['recipient_id'],
    options: ['where' => '(read_at IS NULL)'],
)]
final class Notification
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'notification_id', unique: true)]
        private NotificationId $id,

        #[ORM\Column(name: 'recipient_id', type: 'notification_recipient_id')]
        private RecipientId $recipientId,

        #[ORM\Column(type: 'string', length: 60, enumType: NotificationType::class)]
        private NotificationType $type,

        #[ORM\Column(type: 'string', length: 20, enumType: NotificationCategory::class)]
        private NotificationCategory $category,

        #[ORM\Column(type: 'string', length: 120)]
        private string $title,

        #[ORM\Column(type: 'string', length: 500)]
        private string $body,

        /**
         * Dados estruturados para deep link. O Doctrine não infere o tipo do
         * Value Object, por isso a coluna usa o tipo JSON diretamente.
         *
         * @var array<string, scalar|array<array-key, scalar>|null>
         */
        #[ORM\Column(name: 'payload', type: 'json')]
        private array $payload,

        #[ORM\Column(name: 'deduplication_key', type: 'string', length: 255)]
        private string $deduplicationKey,

        #[ORM\Column(name: 'read_at', type: 'instant', nullable: true)]
        private ?Instant $readAt,

        #[ORM\Column(name: 'created_at', type: 'instant')]
        private Instant $createdAt,
    ) {
    }

    public static function create(
        RecipientId $recipientId,
        NotificationType $type,
        NotificationTitle $title,
        NotificationBody $body,
        NotificationPayload $payload,
        DeduplicationKey $deduplicationKey,
        Instant $createdAt,
    ): self {
        return new self(
            id: NotificationId::generate(),
            recipientId: $recipientId,
            type: $type,
            category: $type->category(),
            title: $title->value(),
            body: $body->value(),
            payload: $payload->toArray(),
            deduplicationKey: $deduplicationKey->value(),
            readAt: null,
            createdAt: $createdAt,
        );
    }

    public function markRead(Instant $now): bool
    {
        if (null !== $this->readAt) {
            return false;
        }

        $this->readAt = $now;

        return true;
    }

    public function id(): NotificationId
    {
        return $this->id;
    }

    public function recipientId(): RecipientId
    {
        return $this->recipientId;
    }

    public function type(): NotificationType
    {
        return $this->type;
    }

    public function category(): NotificationCategory
    {
        return $this->category;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, scalar|array<array-key, scalar>|null>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function deduplicationKey(): string
    {
        return $this->deduplicationKey;
    }

    public function readAt(): ?Instant
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }
}
