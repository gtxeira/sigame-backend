<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Controller;

use App\Gamification\Application\UseCase\ListXpHistory\ListXpHistoryHandler;
use App\Gamification\Application\UseCase\ListXpHistory\XpHistoryItemView;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Presentation\Http\Request\XpHistoryQuery;
use App\Gamification\Presentation\Http\Response\XpHistoryItemResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ApiResponse;
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

#[Route('/api/v1')]
#[OA\Tag(name: 'Gamificação')]
final class XpHistoryController extends AbstractController
{
    public function __construct(
        private readonly ListXpHistoryHandler $listXpHistoryHandler,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.gamification_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
    ) {
    }

    /**
     * Listar meu histórico de XP
     *
     * Lista os lançamentos de XP do usuário autenticado, do mais recente para
     * o mais antigo.
     */
    #[Route('/users/me/progress/history', name: 'app_my_xp_history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(itemModel: XpHistoryItemResponse::class, description: 'Histórico paginado de XP.')]
    #[UnauthorizedResponse]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['perPage' => 'O valor deve estar entre 1 e 30.'],
                summary: 'Paginação inválida',
                description: 'A página ou o tamanho da página são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página solicitada.',
            'perPage' => 'Itens por página.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function list(#[MapQueryString] XpHistoryQuery $query): JsonResponse
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        $userId = $user->getUserIdentifier();
        $this->rateLimitGuard->assertWithin($this->readLimiter, $userId);

        $history = ($this->listXpHistoryHandler)(
            PlayerId::fromString($userId),
            $query->page,
            $query->perPage,
        );

        return ApiResponse::ok(array_map(
            static fn (XpHistoryItemView $item): XpHistoryItemResponse => XpHistoryItemResponse::fromView($item),
            $history->items,
        ))
            ->withPagination($query->page, $query->perPage, $history->total)
            ->toJsonResponse();
    }
}
