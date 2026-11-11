<?php

declare(strict_types=1);

namespace App\Notifications\Presentation\Http\Controller;

use App\Notifications\Application\Service\RealtimeAuthorization;
use App\Notifications\Application\UseCase\AuthorizeRealtime\AuthorizeRealtimeHandler;
use App\Notifications\Application\UseCase\GetUnreadCount\GetUnreadCountHandler;
use App\Notifications\Application\UseCase\ListNotifications\ListNotificationsHandler;
use App\Notifications\Application\UseCase\ListNotifications\ListNotificationsQuery;
use App\Notifications\Application\UseCase\MarkAllNotificationsRead\MarkAllNotificationsReadHandler;
use App\Notifications\Application\UseCase\MarkNotificationRead\MarkNotificationReadHandler;
use App\Notifications\Domain\Notification\Entity\Notification;
use App\Notifications\Domain\Notification\Repository\NotificationSearchCriteria;
use App\Notifications\Domain\Notification\ValueObject\NotificationCategory;
use App\Notifications\Domain\Notification\ValueObject\NotificationId;
use App\Notifications\Domain\Notification\ValueObject\NotificationReadFilter;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Notifications\Presentation\Http\Request\NotificationListQuery;
use App\Notifications\Presentation\Http\Response\MarkAllReadResponse;
use App\Notifications\Presentation\Http\Response\NotificationResponse;
use App\Notifications\Presentation\Http\Response\RealtimeAuthorizationResponse;
use App\Notifications\Presentation\Http\Response\UnreadCountResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Inbox de notificações do usuário autenticado.
 */
#[Route('/api/v1/notifications')]
#[OA\Tag(name: 'Notificações')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly ListNotificationsHandler $listNotificationsHandler,
        private readonly GetUnreadCountHandler $unreadCountHandler,
        private readonly MarkNotificationReadHandler $markReadHandler,
        private readonly MarkAllNotificationsReadHandler $markAllHandler,
        private readonly AuthorizeRealtimeHandler $authorizeRealtimeHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.notifications_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
        #[Autowire(service: 'limiter.notifications_write')]
        private readonly RateLimiterFactoryInterface $writeLimiter,
        #[Autowire(service: 'limiter.notifications_realtime')]
        private readonly RateLimiterFactoryInterface $realtimeLimiter,
    ) {
    }

    /**
     * Listar notificações
     *
     * Lista o inbox do usuário autenticado, com filtro por leitura e categoria.
     */
    #[Route('', name: 'app_notifications_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(
        itemModel: NotificationResponse::class,
        description: 'Lista paginada de notificações.',
    )]
    #[UnauthorizedResponse]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'O filtro de leitura é inválido.',
                field: 'status',
                summary: 'Filtros inválidos',
                description: 'A paginação, o status ou a categoria são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página, a partir de 1.',
            'perPage' => 'Itens por página, no máximo 30.',
            'status' => 'Filtro de leitura.',
            'category' => 'Categoria da notificação.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function list(#[MapQueryString] NotificationListQuery $query): JsonResponse
    {
        $recipient = $this->recipient();
        $this->rateLimitGuard->assertWithin($this->readLimiter, $recipient->value());

        $result = ($this->listNotificationsHandler)(new ListNotificationsQuery(new NotificationSearchCriteria(
            recipientId: $recipient,
            page: $query->page,
            perPage: $query->perPage,
            read: NotificationReadFilter::from($query->status),
            category: null === $query->category ? null : NotificationCategory::from($query->category),
        )));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        $page = $result->value();
        $items = array_map(
            static fn (Notification $notification): NotificationResponse => NotificationResponse::fromEntity($notification),
            $page->items,
        );

        return ApiResponse::ok($items)
            ->withPagination($query->page, $query->perPage, $page->total)
            ->toJsonResponse();
    }

    /**
     * Obter contagem de notificações não lidas
     *
     * Retorna o total de notificações não lidas do usuário autenticado.
     */
    #[Route('/unread-count', name: 'app_notifications_unread_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: UnreadCountResponse::class, description: 'Contagem de notificações não lidas.')]
    #[UnauthorizedResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function unreadCount(): JsonResponse
    {
        $recipient = $this->recipient();
        $this->rateLimitGuard->assertWithin($this->readLimiter, $recipient->value());

        $result = ($this->unreadCountHandler)($recipient);

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (int $total): UnreadCountResponse => new UnreadCountResponse($total)),
        );
    }

    /**
     * Marcar notificação como lida
     *
     * Marca uma notificação do próprio usuário como lida. A operação é
     * idempotente.
     */
    #[Route('/{id}/read', name: 'app_notifications_read', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: NotificationResponse::class, description: 'Notificação marcada como lida.')]
    #[UnauthorizedResponse]
    #[NotFoundResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function markRead(string $id): JsonResponse
    {
        $recipient = $this->recipient();
        $this->rateLimitGuard->assertWithin($this->writeLimiter, $recipient->value());

        $result = ($this->markReadHandler)(NotificationId::fromString($id), $recipient);

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (Notification $notification): NotificationResponse => NotificationResponse::fromEntity($notification)),
        );
    }

    /**
     * Marcar todas as notificações como lidas
     *
     * Marca todas as notificações não lidas do próprio usuário como lidas.
     */
    #[Route('/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: MarkAllReadResponse::class, description: 'Notificações marcadas como lidas.')]
    #[UnauthorizedResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function markAllRead(): JsonResponse
    {
        $recipient = $this->recipient();
        $this->rateLimitGuard->assertWithin($this->writeLimiter, $recipient->value());

        $result = ($this->markAllHandler)($recipient);

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (int $marked): MarkAllReadResponse => new MarkAllReadResponse($marked)),
        );
    }

    /**
     * Autorizar stream de notificações
     *
     * Emite a URL de assinatura e um token de curta duração, somente leitura,
     * restrito ao tópico do usuário autenticado.
     */
    #[Route('/realtime', name: 'app_notifications_realtime', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: RealtimeAuthorizationResponse::class, description: 'Autorização de assinatura do stream.')]
    #[UnauthorizedResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function realtime(): JsonResponse
    {
        $recipient = $this->recipient();
        $this->rateLimitGuard->assertWithin($this->realtimeLimiter, $recipient->value());

        $result = ($this->authorizeRealtimeHandler)($recipient->value());

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (RealtimeAuthorization $authorization): RealtimeAuthorizationResponse => RealtimeAuthorizationResponse::fromAuthorization($authorization)),
        );
    }

    private function recipient(): RecipientId
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        return RecipientId::fromString($user->getUserIdentifier());
    }
}
