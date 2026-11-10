<?php

declare(strict_types=1);

namespace App\Notifications\Presentation\Http\Request;

use App\Notifications\Domain\Notification\ValueObject\NotificationCategory;
use App\Notifications\Domain\Notification\ValueObject\NotificationReadFilter;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filtros e paginação do inbox.
 */
#[OA\Schema(description: 'Filtros da listagem de notificações.')]
final readonly class NotificationListQuery
{
    public function __construct(
        #[OA\Property(description: 'Página, a partir de 1.', example: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Itens por página, no máximo 30.', example: 20)]
        #[Assert\Range(min: 1, max: 30)]
        public int $perPage = 20,

        #[OA\Property(
            description: 'Filtra por estado de leitura.',
            example: NotificationReadFilter::UNREAD->value,
            enum: ['all', 'unread', 'read'],
        )]
        #[Assert\Choice(choices: ['all', 'unread', 'read'], message: 'O filtro de leitura é inválido.')]
        public string $status = NotificationReadFilter::ALL->value,

        #[OA\Property(
            description: 'Filtra por categoria.',
            example: NotificationCategory::GROUP->value,
            nullable: true,
            enum: ['GROUP', 'ACTIVITY', 'GAMIFICATION', 'ACCOUNT'],
        )]
        #[Assert\Choice(
            choices: ['GROUP', 'ACTIVITY', 'GAMIFICATION', 'ACCOUNT'],
            message: 'A categoria informada é inválida.',
        )]
        public ?string $category = null,
    ) {
    }
}
