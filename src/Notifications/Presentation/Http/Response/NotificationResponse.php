<?php

declare(strict_types=1);

namespace App\Notifications\Presentation\Http\Response;

use App\Notifications\Domain\Notification\Entity\Notification;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Notificação do inbox do usuário.')]
final readonly class NotificationResponse
{
    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $data
     */
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(example: 'MEMBERSHIP_REQUESTED')]
        public string $type,

        #[OA\Property(example: 'GROUP')]
        public string $category,

        #[OA\Property(example: 'Nova solicitação de entrada')]
        public string $title,

        #[OA\Property(example: 'João quer entrar no grupo Corrida Centro.')]
        public string $body,

        #[OA\Property(description: 'Dados estruturados para deep link.', type: 'object', example: ['groupId' => '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f'])]
        public array $data,

        #[OA\Property(example: false)]
        public bool $read,

        #[OA\Property(format: 'date-time', example: null, nullable: true)]
        public ?string $readAt,

        #[OA\Property(format: 'date-time', example: '2026-11-10T10:00:00.000+00:00')]
        public string $createdAt,
    ) {
    }

    public static function fromEntity(Notification $notification): self
    {
        return new self(
            id: $notification->id()->value(),
            type: $notification->type()->value,
            category: $notification->category()->value,
            title: $notification->title(),
            body: $notification->body(),
            data: $notification->payload(),
            read: $notification->isRead(),
            readAt: $notification->readAt()?->toIsoStringWithMilliseconds(),
            createdAt: $notification->createdAt()->toIsoStringWithMilliseconds(),
        );
    }
}
