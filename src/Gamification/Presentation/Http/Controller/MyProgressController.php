<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Controller;

use App\Gamification\Application\UseCase\GetMyProgress\GetMyProgressHandler;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Presentation\Http\Response\ProgressResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ApiResponse;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[OA\Tag(name: 'Gamificação')]
final class MyProgressController extends AbstractController
{
    public function __construct(
        private readonly GetMyProgressHandler $getMyProgressHandler,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.gamification_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
    ) {
    }

    /**
     * Obter meu progresso
     *
     * Retorna nível, saldo de XP, barra, ofensiva semanal e meta da semana.
     */
    #[Route('/users/me/progress', name: 'app_my_progress', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: ProgressResponse::class, description: 'Progresso do usuário autenticado.')]
    #[UnauthorizedResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function show(): JsonResponse
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        $userId = $user->getUserIdentifier();
        $this->rateLimitGuard->assertWithin($this->readLimiter, $userId);

        return ApiResponse::ok(
            ProgressResponse::fromView(
                ($this->getMyProgressHandler)(PlayerId::fromString($userId)),
            ),
        )->toJsonResponse();
    }
}
