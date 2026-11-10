<?php

declare(strict_types=1);

namespace App\Gamification\Presentation\Http\Controller;

use App\Gamification\Application\UseCase\GetGamificationRules\GetGamificationRulesHandler;
use App\Gamification\Domain\Rule\GamificationRules;
use App\Gamification\Presentation\Http\Response\GamificationRulesResponse;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[OA\Tag(name: 'Gamificação')]
final class GamificationRulesController extends AbstractController
{
    public function __construct(
        private readonly GetGamificationRulesHandler $getGamificationRulesHandler,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.gamification_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
    ) {
    }

    /**
     * Obter as regras de gamificação
     *
     * Retorna o catálogo de XP, níveis, ofensiva semanal e deleveling da versão vigente.
     */
    #[Route('/gamification/rules', name: 'app_gamification_rules', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: GamificationRulesResponse::class, description: 'Catálogo de regras de gamificação.')]
    #[UnauthorizedResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function show(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        $this->rateLimitGuard->assertWithin($this->readLimiter, $user->getUserIdentifier());

        $response = ApiResponse::ok(
            GamificationRulesResponse::fromView(($this->getGamificationRulesHandler)()),
        )->toJsonResponse();

        $response->setEtag(GamificationRules::VERSION);
        $response->setPrivate();
        $response->setMaxAge(3600);
        $response->isNotModified($request);

        return $response;
    }
}
