<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Controller;

use App\Gamification\Application\UseCase\CelebrateAchievement\CelebrateAchievementHandler;
use App\Gamification\Application\UseCase\ListAchievements\AchievementView;
use App\Gamification\Application\UseCase\ListAchievements\ListAchievementsHandler;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Presentation\Http\Response\AchievementResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ListSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
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
final class AchievementController extends AbstractController
{
    public function __construct(
        private readonly ListAchievementsHandler $listAchievementsHandler,
        private readonly CelebrateAchievementHandler $celebrateAchievementHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.gamification_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
        #[Autowire(service: 'limiter.gamification_write')]
        private readonly RateLimiterFactoryInterface $writeLimiter,
    ) {
    }

    /**
     * Listar minhas conquistas
     *
     * Lista as conquistas já desbloqueadas pelo usuário autenticado.
     */
    #[Route('/users/me/achievements', name: 'app_my_achievements', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[ListSuccessResponse(itemModel: AchievementResponse::class, description: 'Conquistas do usuário autenticado.')]
    #[UnauthorizedResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function list(): JsonResponse
    {
        $userId = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->readLimiter, $userId->value());

        return ApiResponse::ok(array_map(
            static fn (AchievementView $achievement): AchievementResponse => AchievementResponse::fromView($achievement),
            ($this->listAchievementsHandler)($userId),
        ))->toJsonResponse();
    }

    /**
     * Marcar conquista como celebrada
     *
     * Registra que a comemoração da conquista já foi exibida. A operação é
     * idempotente.
     */
    #[Route('/users/me/achievements/{code}/celebrated', name: 'app_my_achievement_celebrated', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: AchievementResponse::class, description: 'Conquista marcada como celebrada.')]
    #[UnauthorizedResponse]
    #[NotFoundResponse(
        examples: [
            'ACHIEVEMENT_NOT_FOUND' => new ApiErrorExample(
                message: 'Conquista não encontrada.',
                field: 'code',
                summary: 'Conquista não encontrada',
                description: 'O código informado não corresponde a uma conquista desbloqueada pelo usuário.',
            ),
        ],
        fields: [
            'code' => 'Código da conquista.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function celebrate(string $code): JsonResponse
    {
        $userId = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->writeLimiter, $userId->value());

        $result = ($this->celebrateAchievementHandler)($userId, $code);

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (AchievementView $achievement): AchievementResponse => AchievementResponse::fromView($achievement)),
        );
    }

    private function actorId(): PlayerId
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        return PlayerId::fromString($user->getUserIdentifier());
    }
}
